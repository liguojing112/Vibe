<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Message;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<Message>
 */
final class MessageRepository extends IRepository
{
    public function __construct(
        protected readonly Message $model
    ) {}

    /**
     * 按 `(发送者, client_msg_id)` 找消息 —— 幂等就靠它（配合唯一索引）。
     */
    public function findByClientMsgId(int $senderId, string $clientMsgId): ?Message
    {
        return $this->model->newQuery()
            ->where('sender_id', $senderId)
            ->where('client_msg_id', $clientMsgId)
            ->first();
    }

    /**
     * 会话里的历史消息（`before_id` 为空时取最新的一页），按 id 倒序取。
     *
     * @return Collection<int,Message>
     */
    public function historyOf(int $conversationId, ?int $beforeId, int $limit): Collection
    {
        $query = $this->model->newQuery()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit);

        if ($beforeId !== null && $beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        /** @var Collection<int,Message> $rows */
        $rows = $query->get();

        return $rows;
    }

    /**
     * 我**收到的**、id 大于游标的消息（重连/回前台时增量补拉）。
     *
     * @return Collection<int,Message>
     */
    public function receivedAfter(int $userId, int $afterId, int $limit): Collection
    {
        /** @var Collection<int,Message> $rows */
        $rows = $this->model->newQuery()
            ->where('receiver_id', $userId)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return $rows;
    }
}
