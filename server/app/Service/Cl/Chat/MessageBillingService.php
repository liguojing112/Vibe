<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat;

use App\Exception\ClBusinessException;
use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PlatformIncomeSource;
use App\Model\Enums\Cl\PointLogType;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\ConversationRepository;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Level\LevelService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Pricing\MessageQuote;
use App\Service\Cl\Pricing\VipFreeQuota;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PlatformIncomeService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Database\Exception\QueryException;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\DbConnection\Db;

/**
 * 发私信计费（docs/06 第 3.3 节的七步）：
 *
 * 1. 校验：双方状态正常、异性、没被拉黑、内容合法
 * 2. 幂等：`client_msg_id` 已存在 → 直接返回已有消息，**不重复扣费**
 * 3. 报价：`MessagePricingService::quote()`
 * 4. 收费：扣男方金币 → 加女方积分 → 记平台收入 → 女方累计收益 + 升级检查
 * 5. 写消息（价格、免费原因、分成、积分都存下来，方便对账）
 * 6. 会话表 `male_sent_count` + 1、更新最后一条消息、对方未读 + 1
 * 7. 事务提交后推送 WebSocket（**T4.4 做**，这里先留口子）
 *
 * 钱只在服务端算（铁律 6），客户端只显示。
 */
final class MessageBillingService
{
    /** 流水里的业务来源标识。 */
    public const REF_TYPE = 'message';

    /** 事务重试次数（并发首次发消息会撞死锁，重试一次基本就能过）。 */
    private const MAX_TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly MessagePricingService $pricingService,
        private readonly SensitiveWordService $sensitiveWordService,
        private readonly VipFreeQuota $vipFreeQuota,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
        private readonly PlatformIncomeService $platformIncomeService,
        private readonly LevelService $levelService,
        private readonly BlockRepository $blockRepository,
        private readonly UserRepository $userRepository,
        private readonly AppConfigService $configService,
    ) {}

    /**
     * @param int $type 1 文字 2 图片（礼物走 `GiftService`，不在这里）
     * @return array{message: array<string,mixed>, fee: null|array<string,mixed>, balance: int}
     */
    public function send(User $sender, int $receiverId, int $type, string $content, string $clientMsgId): array
    {
        $receiver = $this->requireReceiver($receiverId);
        $this->assertCanSend($sender, $receiver, $type, $content);

        // ② 幂等
        $existing = $this->messageRepository->findByClientMsgId($sender->id, $clientMsgId);
        if ($existing instanceof Message) {
            return $this->buildResult($existing, $sender, $receiver);
        }

        try {
            $message = $this->runTransaction(function () use ($sender, $receiver, $type, $content, $clientMsgId): Message {
                $conversation = $this->lockOrCreateConversation($sender, $receiver);

                // 并发下同一个 client_msg_id：唯一索引会挡住，但先查一次能省掉一次异常
                $existing = $this->messageRepository->findByClientMsgId($sender->id, $clientMsgId);
                if ($existing instanceof Message) {
                    return $existing;
                }

                $quote = $this->quoteFor($sender, $receiver, (int) $conversation->male_sent_count);
                $shareRate = $sender->isMale() ? $this->pricingService->shareRateOf($receiver, $this->pricingService->levelRateOf($receiver)) : 0;
                $points = $sender->isMale() ? $this->pricingService->pointsFor($quote->price, $shareRate) : 0;

                // ⑤ 先落消息：扣费的流水要用消息 id 当 ref
                $message = $this->messageRepository->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'client_msg_id' => $clientMsgId,
                    'type' => $type,
                    'content' => $content,
                    'price_coins' => $quote->price,
                    'free_reason' => $quote->freeReason,
                    'receiver_level' => $quote->receiverLevel,
                    'share_rate' => $sender->isMale() ? $shareRate : null,
                    'receiver_points' => $points,
                    'sensitive_hit' => $this->sensitiveWordService->hit($content) ? 1 : 0,
                ]);

                // ④ 收费相关的动作
                if ($quote->price > 0) {
                    $this->coinWalletService->decrease(
                        $sender->id,
                        $quote->price,
                        CoinLogType::MESSAGE,
                        self::REF_TYPE,
                        (int) $message->id,
                        '私信 · ' . $receiver->nickname,
                    );

                    if ($points > 0) {
                        $this->pointWalletService->increase(
                            $receiver->id,
                            $points,
                            PointLogType::MESSAGE_INCOME,
                            self::REF_TYPE,
                            (int) $message->id,
                            '私信收入 · ' . $sender->nickname,
                        );
                    }

                    $this->platformIncomeService->record(
                        PlatformIncomeSource::MESSAGE,
                        (int) $message->id,
                        $sender->id,
                        $receiver->id,
                        $quote->price,
                        $points,
                    );
                }

                if ($quote->freeReason === MessageQuote::FREE_VIP) {
                    // 只有真的用了会员额度才扣今天的次数（命中互关/对子免费就停在前两步）
                    $this->vipFreeQuota->increment($sender->id);
                }

                if ($points > 0) {
                    // 累计收益（升级用）只算私信+礼物分成，任务奖励不算
                    $receiver->total_earned_points = (int) $receiver->total_earned_points + $points;
                    $receiver->save();
                    $this->levelService->checkUpgrade($receiver);
                }

                // ⑥ 会话
                $this->touchConversation($conversation, $message, $sender, $receiver);

                return $message;
            });
        } catch (QueryException) {
            // 重试还是失败（并发同 client_msg_id 时对方已经写好了）→ 直接返回那一条
            $existing = $this->messageRepository->findByClientMsgId($sender->id, $clientMsgId);
            if ($existing instanceof Message) {
                return $this->buildResult($existing, $sender, $receiver);
            }
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '消息发送失败，请重试');
        }

        return $this->buildResult($message, $sender, $receiver);
    }

    // ---------- 内部 ----------

    /**
     * 一个事务跑完七步；**死锁 / 唯一冲突这类「重试就好」的错自动重来**。
     *
     * 为什么必须重试：两个人同时给同一个人发第一条消息时，两个协程会同时 `insert cl_conversation`，
     * 撞上唯一索引/间隙锁，MySQL 会挑一个当事务回滚（1213 死锁）。重试时对方已经建好了会话，
     * 这一轮就能读到它继续走。
     */
    private function runTransaction(callable $callback): Message
    {
        $attempt = 0;
        while (true) {
            ++$attempt;
            try {
                return Db::transaction($callback);
            } catch (QueryException $e) {
                if ($attempt >= self::MAX_TRANSACTION_ATTEMPTS || ! $this->isRetryable($e)) {
                    throw $e;
                }
                usleep(random_int(1000, 20000));
            }
        }
    }

    private function isRetryable(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Deadlock')
            || str_contains($message, 'Lock wait timeout')
            || str_contains($message, 'Duplicate entry');
    }

    /**
     * 拿会话并加行锁；还没有就建一条（并发首次发消息时靠唯一索引兜底）。
     */
    private function lockOrCreateConversation(User $sender, User $receiver): \App\Model\Cl\Conversation
    {
        $conversation = $this->conversationRepository->lockByPair($sender->id, $receiver->id);
        if ($conversation !== null) {
            return $conversation;
        }

        [$low, $high] = \App\Model\Cl\Conversation::pair($sender->id, $receiver->id);
        $maleId = $sender->isMale() ? $sender->id : $receiver->id;
        $femaleId = $sender->isMale() ? $receiver->id : $sender->id;

        try {
            return $this->conversationRepository->createPair($low, $high, $maleId, $femaleId);
        } catch (UniqueConstraintViolationException) {
            // 另一个请求刚好建好了，锁住它继续
            $conversation = $this->conversationRepository->lockByPair($sender->id, $receiver->id);
            if ($conversation !== null) {
                return $conversation;
            }
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会话创建失败，请重试');
        }
    }

    /**
     * 女 → 男永远免费、不给积分（docs/06 第 3.2 节）。
     */
    private function quoteFor(User $sender, User $receiver, int $maleSentCount): MessageQuote
    {
        if ($sender->isFemale()) {
            return new MessageQuote(
                price: 0,
                freeReason: MessageQuote::FREE_FEMALE_SEND,
                pairFreeRemaining: 0,
                vipFreeRemaining: 0,
                receiverLevel: (int) ($receiver->level ?? 1),
                unitPrice: 0,
            );
        }

        return $this->pricingService->quote($sender, $receiver, $maleSentCount);
    }

    private function touchConversation(
        \App\Model\Cl\Conversation $conversation,
        Message $message,
        User $sender,
        User $receiver,
    ): void {
        $unreadColumn = $conversation->unreadColumnFor($receiver->id);
        $conversation->{$unreadColumn} = (int) $conversation->{$unreadColumn} + 1;

        // male_sent_count 只算「男方发给女方的普通消息」（礼物不计，06 第 10 节场景 16）
        if ($sender->isMale() && $message->isNormalMessage()) {
            $conversation->male_sent_count = (int) $conversation->male_sent_count + 1;
        }

        $conversation->last_message_id = (int) $message->id;
        $conversation->last_message_at = $message->created_at;
        // 新消息让双方「删过的」会话重新出现
        $conversation->low_deleted_at = null;
        $conversation->high_deleted_at = null;
        $conversation->save();
    }

    /**
     * @return array{message: array<string,mixed>, fee: null|array<string,mixed>, balance: int}
     */
    private function buildResult(Message $message, User $sender, User $receiver): array
    {
        if (! $sender->isMale()) {
            return [
                'message' => $this->messagePayload($message),
                'fee' => null,
                'balance' => $this->pointWalletService->summaryOf($sender->id)['balance'],
            ];
        }

        $conversation = $this->conversationRepository->findByPair($sender->id, $receiver->id);
        $sentCount = $conversation === null ? 0 : (int) $conversation->male_sent_count;
        // 发完这条之后的「下一条」报价（docs/05 第 4.5 节的 fee 结构）
        $next = $this->pricingService->quote($sender, $receiver, $sentCount);
        $balance = $this->coinWalletService->balanceOf($sender->id);

        return [
            'message' => $this->messagePayload($message),
            'fee' => [
                'next_price' => $next->price,
                'free_reason' => $next->freeReason,
                'pair_free_left' => $next->pairFreeRemaining,
                'vip_free_left' => $next->vipFreeRemaining,
                'balance' => $balance,
                'strip_text' => $this->pricingService->stripText($next, $balance),
            ],
            'balance' => $balance,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function messagePayload(Message $message): array
    {
        return [
            'id' => (int) $message->id,
            'conversation_id' => (int) $message->conversation_id,
            'sender_id' => (int) $message->sender_id,
            'receiver_id' => (int) $message->receiver_id,
            'client_msg_id' => (string) $message->client_msg_id,
            'type' => (int) $message->type,
            'content' => (string) $message->content,
            'price_coins' => (int) $message->price_coins,
            'free_reason' => $message->free_reason,
            'receiver_points' => (int) $message->receiver_points,
            'sensitive_hit' => (int) $message->sensitive_hit,
            'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function requireReceiver(int $receiverId): User
    {
        $receiver = $this->userRepository->findById($receiverId);
        if (! $receiver instanceof User || $receiver->status !== User::STATUS_NORMAL) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $receiver;
    }

    private function assertCanSend(User $sender, User $receiver, int $type, string $content): void
    {
        if ($sender->id === $receiver->id) {
            throw new ClBusinessException(BizCode::SAME_GENDER_NOT_ALLOWED, '不能给自己发消息');
        }

        // 同性之间不能私信（docs/01 第 7 节）
        if ($sender->gender === $receiver->gender) {
            throw new ClBusinessException(BizCode::SAME_GENDER_NOT_ALLOWED);
        }

        // 任一方向拉黑都发不出去（docs/01 第 7 节）
        if ($this->blockRepository->blockedEitherWay($sender->id, $receiver->id)) {
            throw new ClBusinessException(BizCode::BLOCKED_BY_TARGET);
        }

        $content = trim($content);
        if ($content === '') {
            throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT);
        }

        if ($type === Message::TYPE_TEXT) {
            $maxLength = (int) $this->configService->get('chat.message_max_length', 500);
            if (mb_strlen($content) > $maxLength) {
                throw new ClBusinessException(BizCode::INVALID_MESSAGE_CONTENT, sprintf('消息最多 %d 个字', $maxLength));
            }
        }
    }
}
