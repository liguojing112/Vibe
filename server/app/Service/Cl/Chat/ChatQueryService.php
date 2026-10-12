<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat;

use App\Exception\ClBusinessException;
use App\Model\Cl\Conversation;
use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\ConversationRepository;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\UserRepository;
use App\Repository\Cl\VisitRepository;
use Hyperf\Collection\Collection;

/**
 * 聊天查询（docs/05 第 4.5 节）：会话列表、删会话、进聊天页、历史消息、已读、未读数、增量补拉。
 *
 * 发消息的计费在 `MessageBillingService`，这里只读不写钱。
 *
 * **本地化口径**（客户 2026-10-11 确认，docs/10）：App 以本地库为聊天记录主数据源，
 * 这几个接口只服务于「首次拉取 + 静默刷新 + 增量补拉」，服务端不是展示数据源。
 */
final class ChatQueryService
{
    /** 会话列表每页条数（docs/05 第 1 节分页约定）。 */
    public const PAGE_SIZE = 20;

    /** 历史消息默认每页条数（docs/05 第 4.5 节的 limit 默认值）。 */
    public const MESSAGE_PAGE_SIZE = 30;

    /** 单次最多能取多少条（防止客户端传个 100000 把库拖死）。 */
    public const MESSAGE_MAX_LIMIT = 100;

    /** 增量补拉单次上限。 */
    public const SYNC_MAX_LIMIT = 200;

    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly MessageRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly BlockRepository $blockRepository,
        private readonly VisitRepository $visitRepository,
        private readonly ChatPresenter $presenter,
    ) {}

    /**
     * 会话列表。已删的、被拉黑的都不出现。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function conversations(User $user, int $page): array
    {
        $exclude = $this->blockRepository->relatedBlockedIds($user->id);
        $conversations = $this->conversationRepository->listForUser(
            $user->id,
            max(0, ($page - 1) * self::PAGE_SIZE),
            self::PAGE_SIZE,
            $exclude,
        );

        $total = $this->conversationRepository->countForUser($user->id, $exclude);
        if ($conversations === []) {
            return ['list' => [], 'total' => $total, 'page' => $page];
        }

        // 对方资料 + 最后一条消息都批量取，别在循环里查库
        $peerIds = [];
        $messageIds = [];
        foreach ($conversations as $conversation) {
            $peerIds[] = $conversation->peerIdFor($user->id);
            if ($conversation->last_message_id !== null) {
                $messageIds[] = (int) $conversation->last_message_id;
            }
        }
        $peers = $this->userRepository->listByIds($peerIds);
        $lastMessages = $this->messageRepository->byIds($messageIds)->keyBy('id');

        $list = [];
        foreach ($conversations as $conversation) {
            $peer = $peers->get($conversation->peerIdFor($user->id));
            $lastMessage = $conversation->last_message_id === null
                ? null
                : $lastMessages->get((int) $conversation->last_message_id);

            $list[] = [
                'id' => (int) $conversation->id,
                'peer' => $peer instanceof User ? $this->presenter->peer($peer) : null,
                'last_message' => $lastMessage instanceof Message ? $this->presenter->message($lastMessage) : null,
                'last_message_at' => $conversation->last_message_at?->format('Y-m-d H:i:s'),
                'unread' => $conversation->unreadFor($user->id),
            ];
        }

        return ['list' => $list, 'total' => $total, 'page' => $page];
    }

    /**
     * 删除会话（**只对自己隐藏**）。重复删当成功，客户端不用去区分。
     */
    public function deleteConversation(User $user, int $conversationId): void
    {
        $conversation = $this->requireParticipant($user, $conversationId);

        $this->conversationRepository->hideFor($conversation, $user->id);
    }

    /**
     * 进聊天页时调用（docs/05 第 4.5 节）：会话 id（没有则 null，第一次发消息时才建）+ 收费信息。
     *
     * @return array{conversation_id: null|int, peer: array<string,mixed>, unread: int, fee: null|array<string,mixed>}
     */
    public function with(User $user, int $peerId): array
    {
        $peer = $this->requireChattablePeer($user, $peerId);

        $conversation = $this->conversationRepository->findByPair($user->id, $peerId);

        return [
            'conversation_id' => $conversation?->id,
            'peer' => $this->presenter->peer($peer),
            'unread' => $conversation === null ? 0 : $conversation->unreadFor($user->id),
            // 报价取「下一条」：male_sent_count 是已发条数，算出来正好是还没发的那一条
            'fee' => $this->presenter->fee($user, $peer, (int) ($conversation->male_sent_count ?? 0)),
        ];
    }

    /**
     * 历史消息。**按 id 倒序返回**（最新在前），上拉加载更早的传 before_id。
     *
     * @return array{list: array<int,array<string,mixed>>, has_more: bool}
     */
    public function messages(User $user, int $conversationId, ?int $beforeId, int $limit): array
    {
        $this->requireParticipant($user, $conversationId);

        return $this->page($this->messageRepository->historyOf($conversationId, $beforeId, $limit + 1), $limit);
    }

    /**
     * 标记已读，并把**最新的未读总览**一起返回 —— 客户端点进聊天页后要立刻更新底部角标，
     * 不该再多打一次未读接口。
     *
     * @return array{conversation: int, visitor: int, notice: int, total: int}
     */
    public function markRead(User $user, int $conversationId): array
    {
        $conversation = $this->requireParticipant($user, $conversationId);

        $this->conversationRepository->clearUnread($conversation, $user->id);
        $this->messageRepository->markRead($conversationId, $user->id);

        return $this->unread($user);
    }

    /**
     * 未读总览（docs/05 第 4.5 节）：会话 + 访客 + 系统通知，分别返回。
     *
     * @return array{conversation: int, visitor: int, notice: int, total: int}
     */
    public function unread(User $user): array
    {
        $conversation = $this->conversationRepository->unreadTotalFor(
            $user->id,
            $this->blockRepository->relatedBlockedIds($user->id),
        );
        $visitor = $this->visitRepository->countUnread($user->id);
        // 系统通知表（cl_notice）要到 P6 的 T6.6 才建，这里先恒为 0，字段位置先占住
        $notice = 0;

        return [
            'conversation' => $conversation,
            'visitor' => $visitor,
            'notice' => $notice,
            'total' => $conversation + $visitor + $notice,
        ];
    }

    /**
     * 增量补拉：我**收到的**、id 大于游标的消息（重连 / 回前台时调用，客户端把它写进本地库游标）。
     *
     * 只拉收到的、不拉自己发的 —— 自己发的在乐观 UI 那时就已经落本地库了。
     *
     * @return array{list: array<int,array<string,mixed>>, max_id: int, has_more: bool, unread: array<string,int>}
     */
    public function sync(User $user, int $afterId, int $limit): array
    {
        $rows = $this->messageRepository->receivedAfter($user->id, $afterId, $limit + 1);
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        // 升序返回，游标就是最后一条的 id；一条都没有时游标不动
        $maxId = $afterId;
        foreach ($rows as $row) {
            $maxId = max($maxId, (int) $row->id);
        }

        return [
            'list' => $this->presenter->messages($rows),
            'max_id' => $maxId,
            'has_more' => $hasMore,
            'unread' => $this->unread($user),
        ];
    }

    /**
     * @param Collection<int,Message> $rows
     * @return array{list: array<int,array<string,mixed>>, has_more: bool}
     */
    private function page(Collection $rows, int $limit): array
    {
        // 多取一条来判断还有没有更早的，返回前把它丢掉
        $hasMore = $rows->count() > $limit;

        return [
            'list' => $this->presenter->messages($hasMore ? $rows->take($limit) : $rows),
            'has_more' => $hasMore,
        ];
    }

    /**
     * 会话必须是「我的」，否则一律当不存在 —— 不区分「没这条」和「不是你的」，
     * 免得拿 id 就能探出别人有没有会话。
     */
    private function requireParticipant(User $user, int $conversationId): Conversation
    {
        $conversation = $this->conversationRepository->findById($conversationId);
        if (! $conversation instanceof Conversation || ! $conversation->involves($user->id)) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND, '会话不存在');
        }

        return $conversation;
    }

    /**
     * 能聊的对象：存在、状态正常、异性、两个方向都没有拉黑。
     */
    private function requireChattablePeer(User $user, int $peerId): User
    {
        if ($user->id === $peerId) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        $peer = $this->userRepository->findById($peerId);
        if (! $peer instanceof User || $peer->status !== User::STATUS_NORMAL) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        if ($peer->gender === $user->gender) {
            throw new ClBusinessException(BizCode::SAME_GENDER_NOT_ALLOWED);
        }

        // 拉黑后互相不可见：就当对方不存在
        if ($this->blockRepository->blockedEitherWay($user->id, $peerId)) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $peer;
    }
}
