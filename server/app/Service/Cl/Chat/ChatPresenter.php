<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat;

use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Repository\Cl\DiscoverRepository;
use App\Repository\Cl\GiftRepository;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Wallet\CoinWalletService;
use Hyperf\Carbon\Carbon;

/**
 * 聊天相关的**出参形状**只在这里拼一次。
 *
 * 为什么单独放一个类：同一条消息会从三个地方发给客户端 —— `/chat/send` 的返回值、
 * `/chat/messages` 与 `/chat/sync` 的列表、以及 T4.4 的 WebSocket `message.new` 推送。
 * 三处字段必须一模一样（客户端只写一套解码），所以形状只有这一个来源。
 */
final class ChatPresenter
{
    public function __construct(
        private readonly MessagePricingService $pricingService,
        private readonly CoinWalletService $coinWalletService,
        private readonly GiftRepository $giftRepository,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function message(Message $message): array
    {
        $data = [
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

        // 礼物消息的 content 只有礼物名，气泡里要显示图标得单独补一次
        if ((int) $message->type === Message::TYPE_GIFT) {
            $data['gift_icon'] = $this->giftRepository->iconByName((string) $message->content);
        }

        return $data;
    }

    /**
     * @param iterable<int,Message> $messages
     * @return array<int,array<string,mixed>>
     */
    public function messages(iterable $messages): array
    {
        $list = [];
        foreach ($messages as $message) {
            $list[] = $this->message($message);
        }

        return $list;
    }

    /**
     * 收费信息（docs/05 第 4.5 节的 `fee` 结构）。**只对男用户有值，女用户返回 null**（验收项之一）。
     *
     * @param int $maleSentCount 这对男女之间男方已发送的普通消息条数；报价取的是「下一条」
     */
    public function fee(User $viewer, User $peer, int $maleSentCount): ?array
    {
        if (! $viewer->isMale()) {
            return null;
        }

        $quote = $this->pricingService->quote($viewer, $peer, $maleSentCount);
        $balance = $this->coinWalletService->balanceOf($viewer->id);

        return [
            'next_price' => $quote->price,
            'free_reason' => $quote->freeReason,
            'pair_free_left' => $quote->pairFreeRemaining,
            'vip_free_left' => $quote->vipFreeRemaining,
            'balance' => $balance,
            'strip_text' => $this->pricingService->stripText($quote, $balance),
        ];
    }

    /**
     * 列表里的「对方简要资料」。字段和发现页卡片取齐，客户端能复用同一套渲染。
     *
     * @return array<string,mixed>
     */
    public function peer(User $user): array
    {
        $onlineSince = Carbon::now()->subMinutes(DiscoverRepository::ONLINE_MINUTES);

        return [
            'id' => (int) $user->id,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'age' => $user->age(),
            'gender' => (int) $user->gender,
            'level' => (int) ($user->level ?? 1),
            'online' => $user->last_active_at !== null && $user->last_active_at->greaterThanOrEqualTo($onlineSince),
        ];
    }
}
