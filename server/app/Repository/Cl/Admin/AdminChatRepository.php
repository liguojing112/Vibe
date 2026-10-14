<?php

declare(strict_types=1);

namespace App\Repository\Cl\Admin;

use App\Model\Cl\Conversation;
use App\Model\Cl\Message;
use Hyperf\Database\Model\Builder;

/**
 * 后台「聊天管理」的查询（docs/07 第 3.6、3.7 节）。
 *
 * 会话和消息都要带上**双方是谁**（`cl_message` 里只有 id），所以两边都 join `cl_user`。
 * `cl_user` 不是钱包表，不违反 `check-money.sh` 那条规矩。
 */
final class AdminChatRepository
{
    /** 会话列表和「按用户搜」都用同一套筛选项。 */
    public const FILTER_KEYS = ['user_id', 'created_from', 'created_to'];

    public function __construct(
        private readonly Conversation $conversationModel,
        private readonly Message $messageModel,
    ) {}

    /**
     * 会话列表（docs/07 第 3.6 节：男方、女方、男方已发条数、最后消息时间）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function conversationPage(array $filters, int $page, int $pageSize): array
    {
        $total = $this->conversationQuery($filters)->count();
        $rows = $this->conversationQuery($filters)
            ->orderByDesc('cl_conversation.last_message_at')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return ['list' => $this->toRows($rows), 'total' => $total];
    }

    public function findConversation(int $id): ?Conversation
    {
        /** @var null|Conversation $row */
        $row = $this->conversationModel->newQuery()->whereKey($id)->first();

        return $row;
    }

    /**
     * 某个会话里的消息（后台看全部，不分「我收到的」）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function messagePage(int $conversationId, int $page, int $pageSize): array
    {
        $total = $this->messageQuery()->where('cl_message.conversation_id', $conversationId)->count();
        $rows = $this->messageQuery()
            ->where('cl_message.conversation_id', $conversationId)
            ->orderByDesc('cl_message.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return ['list' => $this->toRows($rows), 'total' => $total];
    }

    /**
     * 待审消息（`review_status = 1`，docs/07 第 3.7 节）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pendingReviewPage(int $page, int $pageSize): array
    {
        $total = $this->messageQuery()->where('cl_message.review_status', Message::REVIEW_PENDING)->count();
        $rows = $this->messageQuery()
            ->where('cl_message.review_status', Message::REVIEW_PENDING)
            ->orderByDesc('cl_message.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        return ['list' => $this->toRows($rows), 'total' => $total];
    }

    /** 举报详情里「点击查看上下文」用：这条消息前后各几条。 */
    public function contextAround(int $conversationId, int $messageId, int $limit): array
    {
        $rows = $this->messageQuery()
            ->where('cl_message.conversation_id', $conversationId)
            ->whereBetween('cl_message.id', [$messageId - $limit, $messageId + $limit])
            ->orderBy('cl_message.id')
            ->get();

        return $this->toRows($rows);
    }

    public function findMessage(int $id): ?Message
    {
        /** @var null|Message $row */
        $row = $this->messageModel->newQuery()->whereKey($id)->first();

        return $row;
    }

    /**
     * 批量改审核状态（标记正常 / 标记违规）。
     *
     * @param array<int,int> $ids
     */
    public function markReview(array $ids, int $status): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->messageModel->newQuery()->whereIn('id', $ids)->update(['review_status' => $status]);
    }

    private function conversationQuery(array $filters): Builder
    {
        // `@var`：phpstan 认为 join 之后是 Query\Builder，运行期一直是模型的 Builder
        /** @var Builder $query */
        $query = $this->conversationModel->newQuery()
            ->join('cl_user as m', 'm.id', '=', 'cl_conversation.male_id')
            ->join('cl_user as f', 'f.id', '=', 'cl_conversation.female_id')
            ->select([
                'cl_conversation.id', 'cl_conversation.male_id', 'cl_conversation.female_id',
                'cl_conversation.male_sent_count', 'cl_conversation.last_message_at',
                'cl_conversation.last_message_id', 'cl_conversation.created_at',
                'm.nickname as male_nickname', 'm.avatar as male_avatar',
                'f.nickname as female_nickname', 'f.avatar as female_avatar',
            ]);

        // 「用户 ID（任一方）」
        $userId = $filters['user_id'] ?? null;
        if ($userId !== null && $userId !== '') {
            $id = (int) $userId;
            $query->where(static fn (Builder $q) => $q->where('cl_conversation.male_id', $id)->orWhere('cl_conversation.female_id', $id));
        }

        if (! empty($filters['created_from'])) {
            $query->where('cl_conversation.created_at', '>=', $this->dayStart((string) $filters['created_from']));
        }
        if (! empty($filters['created_to'])) {
            $query->where('cl_conversation.created_at', '<=', $this->dayEnd((string) $filters['created_to']));
        }

        return $query;
    }

    private function messageQuery(): Builder
    {
        /** @var Builder $query */
        $query = $this->messageModel->newQuery()
            ->join('cl_user as s', 's.id', '=', 'cl_message.sender_id')
            ->join('cl_user as r', 'r.id', '=', 'cl_message.receiver_id')
            ->select([
                'cl_message.id', 'cl_message.conversation_id', 'cl_message.sender_id', 'cl_message.receiver_id',
                'cl_message.type', 'cl_message.content', 'cl_message.price_coins', 'cl_message.free_reason',
                'cl_message.receiver_points', 'cl_message.sensitive_hit', 'cl_message.review_status',
                'cl_message.created_at',
                's.nickname as sender_nickname', 's.avatar as sender_avatar', 's.gender as sender_gender',
                'r.nickname as receiver_nickname', 'r.avatar as receiver_avatar', 'r.gender as receiver_gender',
            ]);

        return $query;
    }

    private function dayStart(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 00:00:00' : $value;
    }

    /** 「截止到某天」要包含那一天，不然那天的会话会被整片漏掉。 */
    private function dayEnd(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 23:59:59' : $value;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function toRows(iterable $rows): array
    {
        $list = [];
        foreach ($rows as $row) {
            /** @var object $row */
            $list[] = method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
        }

        return $list;
    }
}
