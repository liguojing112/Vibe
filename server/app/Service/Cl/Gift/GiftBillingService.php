<?php

declare(strict_types=1);

namespace App\Service\Cl\Gift;

use App\Exception\ClBusinessException;
use App\Model\Cl\Gift;
use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PlatformIncomeSource;
use App\Model\Enums\Cl\PointLogType;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\GiftRecordRepository;
use App\Repository\Cl\GiftRepository;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Chat\ChatPresenter;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Chat\ConversationService;
use App\Service\Cl\Level\LevelService;
use App\Service\Cl\Notice\NoticeService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Push\PushService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PlatformIncomeService;
use App\Service\Cl\Wallet\PointWalletService;

/**
 * 送礼物（docs/06 第 4 节）。
 *
 * 一个事务里做完：扣男方金币 → 女方加积分 → 记平台收入 → 检查升级 → 写赠送记录 → 在会话里插一条礼物消息。
 *
 * 两个容易搞错的点：
 * 1. **礼物消息本身不再按私信收费**（`price_coins = 0`），也**不计入 `male_sent_count`**
 *    （`docs/06` 第 10 节场景 16）—— 否则用户送个礼物反而白少一次「前 N 条免费」；
 * 2. 积分用 `share.gift_rate`（默认 3400）而不是女方等级的私信分成：礼物分成**不随等级变化**。
 */
final class GiftBillingService
{
    /** 流水/收入里的业务来源标识。 */
    public const REF_TYPE = 'gift';

    public function __construct(
        private readonly GiftRepository $giftRepository,
        private readonly GiftRecordRepository $recordRepository,
        private readonly MessageRepository $messageRepository,
        private readonly BlockRepository $blockRepository,
        private readonly UserRepository $userRepository,
        private readonly ConversationService $conversationService,
        private readonly MessagePricingService $pricingService,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
        private readonly PlatformIncomeService $platformIncomeService,
        private readonly LevelService $levelService,
        private readonly NoticeService $noticeService,
        private readonly PushService $pushService,
        private readonly ChatPresenter $presenter,
    ) {}

    use RetriesTransactions;

    /**
     * @return array{message: array<string,mixed>, balance: int}
     */
    public function send(User $sender, int $receiverId, int $giftId, string $clientOrderId): array
    {
        $receiver = $this->requireReceiver($sender, $receiverId);
        $gift = $this->requireGift($giftId);

        // 幂等：同一个订单号已经送过 → 直接返回那一次的结果，不重复扣钱
        $existing = $this->recordRepository->findByOrderId($sender->id, $clientOrderId);
        if ($existing !== null && $existing->message_id !== null) {
            $message = $this->messageRepository->findById($existing->message_id);
            if ($message instanceof Message) {
                return [
                    'message' => $this->presenter->message($message),
                    'balance' => $this->coinWalletService->balanceOf($sender->id),
                ];
            }
        }

        $message = $this->runTransaction(function () use ($sender, $receiver, $gift, $clientOrderId): Message {
            $conversation = $this->conversationService->lockOrCreate($sender, $receiver);

            $rate = $this->pricingService->giftShareRateOf($receiver);
            $points = $this->pricingService->pointsFor((int) $gift->price_coins, $rate);

            // 先落赠送记录：扣币/加分的流水都要用它当 ref
            $record = $this->recordRepository->create([
                'client_order_id' => $clientOrderId,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'gift_id' => $gift->id,
                'gift_name' => $gift->name,
                'price_coins' => (int) $gift->price_coins,
                'share_rate' => $rate,
                'receiver_points' => $points,
            ]);

            // 余额不足会抛 20001，整个事务回滚（记录、消息、积分都不会留下）
            $this->coinWalletService->decrease(
                $sender->id,
                (int) $gift->price_coins,
                CoinLogType::GIFT,
                self::REF_TYPE,
                (int) $record->id,
                '礼物 · ' . $gift->name,
            );

            if ($points > 0) {
                $this->pointWalletService->increase(
                    $receiver->id,
                    $points,
                    PointLogType::GIFT_INCOME,
                    self::REF_TYPE,
                    (int) $record->id,
                    '礼物收入 · ' . $gift->name,
                );
            }

            $this->platformIncomeService->record(
                PlatformIncomeSource::GIFT,
                (int) $record->id,
                $sender->id,
                $receiver->id,
                (int) $gift->price_coins,
                $points,
            );

            if ($points > 0) {
                $receiver->total_earned_points = (int) $receiver->total_earned_points + $points;
                $receiver->save();
                $this->levelService->checkUpgrade($receiver);
            }

            // 会话里插一条礼物消息：**内容只有礼物名**，价格是 0（礼物不按私信计费）。
            // 客户端要显示图标时用礼物名去 `/gift/list` 里对（那边有 icon）。
            $message = $this->messageRepository->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'client_msg_id' => $clientOrderId,
                'type' => Message::TYPE_GIFT,
                'content' => (string) $gift->name,
                'price_coins' => 0,
                'free_reason' => null,
                'receiver_level' => (int) ($receiver->level ?? 1),
                'share_rate' => $rate,
                'receiver_points' => $points,
                'sensitive_hit' => 0,
            ]);

            $record->message_id = (int) $message->id;
            $record->save();

            // male_sent_count 不会 +1（礼物不算普通消息）
            $this->conversationService->touch($conversation, $message, $sender, $receiver);

            // 收礼人收到一条互动通知（P10）。写在事务里：礼物送成了通知就一定有，
            // 不会出现「钱扣了、礼收了、通知没发出来」。
            $this->noticeService->giftReceived($receiver, $sender, (string) $gift->name);

            return $message;
        });

        // 事务提交后推送（和发私信一样：提前推客户端可能查不到这条消息）
        $this->pushService->messageReceived($message, $receiver);

        return [
            'message' => $this->presenter->message($message),
            'balance' => $this->coinWalletService->balanceOf($sender->id),
        ];
    }

    // ---------- 内部 ----------

    /**
     * 收礼人：存在、状态正常、异性（**同性不能送礼物**，docs/01 第 7 节）、没被拉黑。
     */
    private function requireReceiver(User $sender, int $receiverId): User
    {
        if ($sender->id === $receiverId) {
            throw new ClBusinessException(BizCode::SAME_GENDER_NOT_ALLOWED, '不能给自己送礼物');
        }

        // 只有男用户能送（docs/06 第 4 节）
        if (! $sender->isMale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '只有男用户可以送礼物');
        }

        $receiver = $this->userRepository->findById($receiverId);
        if (! $receiver instanceof User || $receiver->status !== User::STATUS_NORMAL) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        if ($receiver->isMale()) {
            throw new ClBusinessException(BizCode::SAME_GENDER_NOT_ALLOWED);
        }

        if ($this->blockRepository->blockedEitherWay($sender->id, $receiverId)) {
            throw new ClBusinessException(BizCode::BLOCKED_BY_TARGET);
        }

        return $receiver;
    }

    private function requireGift(int $giftId): Gift
    {
        $gift = $this->giftRepository->findEnabled($giftId);
        if (! $gift instanceof Gift) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '礼物不存在或已下架');
        }

        return $gift;
    }
}
