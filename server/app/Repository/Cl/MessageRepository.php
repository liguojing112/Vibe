<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Message;
use App\Repository\IRepository;
use Hyperf\Carbon\Carbon;
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

    /**
     * 按 id 批量取（会话列表要显示每条会话的「最后一条消息」，一次查完，别在循环里查库）。
     *
     * @param array<int,int> $ids
     * @return Collection<int,Message>
     */
    public function byIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection([]);
        }

        /** @var Collection<int,Message> $rows */
        $rows = $this->model->newQuery()->whereIn('id', $ids)->get();

        return $rows;
    }

    /**
     * 超期、可以物理删掉的**普通消息** id（docs/04 第 3 节）。
     *
     * 三个条件缺一不可：
     * - 只清文字/图片（礼物牵着钱、系统消息牵着通知，留着）；
     * - `sensitive_hit = 0`：敏感词命中的是监管证据，不删；
     * - 没有被举报关联（`cl_report.message_id`）：举报取证要长期保留。
     *
     * 用 `NOT EXISTS` 而不是先查一批 id 再 `NOT IN`：被举报的消息可能很多，
     * 拉成数组再塞进 SQL 又慢又容易踩参数上限。
     *
     * @return array<int,int>
     */
    public function expiredIds(string $cutoff, int $limit): array
    {
        $ids = $this->model->newQuery()
            ->where('created_at', '<', $cutoff)
            ->whereIn('type', [Message::TYPE_TEXT, Message::TYPE_IMAGE])
            ->where('sensitive_hit', 0)
            ->whereRaw('NOT EXISTS (SELECT 1 FROM cl_report WHERE cl_report.message_id = cl_message.id)')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        return array_map(static fn (mixed $id): int => (int) $id, $ids);
    }

    /**
     * 这批消息分别属于哪些会话（删完要修这些会话的 `last_message_id`）。
     *
     * @param array<int,int> $ids
     * @return array<int,int>
     */
    public function conversationIdsOf(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $ids = $this->model->newQuery()
            ->whereIn('id', $ids)
            ->pluck('conversation_id')
            ->all();

        return array_map(static fn (mixed $id): int => (int) $id, $ids);
    }

    /**
     * 物理删除（留存清理专用）。
     *
     * @param array<int,int> $ids
     */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->model->newQuery()->whereIn('id', $ids)->delete();
    }

    /**
     * 把我收到的、这个会话里的消息标记成已读（`read_at` 是监管留痕，未读数在会话表上）。
     */
    public function markRead(int $conversationId, int $receiverId): int
    {
        return $this->model->newQuery()
            ->where('conversation_id', $conversationId)
            ->where('receiver_id', $receiverId)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * 这个时间段里给多少个**不同的人**发过私信（「和新朋友打招呼」任务用，docs/06 第 7 节）。
     *
     * 只算文字和图片：**礼物消息不算**。礼物一直和私信是两套口径
     * （`GiftBillingService` 特意不把礼物计进 `male_sent_count`），这里沿用同一条线。
     */
    public function countPartnersBetween(int $senderId, string $from, string $to): int
    {
        return $this->model->newQuery()
            ->where('sender_id', $senderId)
            ->whereIn('type', [Message::TYPE_TEXT, Message::TYPE_IMAGE])
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->distinct()
            ->count('receiver_id');
    }
}
