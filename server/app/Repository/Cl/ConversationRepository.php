<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Conversation;
use App\Repository\IRepository;
use Hyperf\Carbon\Carbon;
use Hyperf\Database\Model\Builder;
use Hyperf\DbConnection\Db;

/**
 * @extends IRepository<Conversation>
 */
final class ConversationRepository extends IRepository
{
    public function __construct(
        protected readonly Conversation $model
    ) {}

    public function findByPair(int $a, int $b): ?Conversation
    {
        [$low, $high] = Conversation::pair($a, $b);

        return $this->model->newQuery()
            ->where('user_low_id', $low)
            ->where('user_high_id', $high)
            ->first();
    }

    /**
     * 锁住这对人的会话行。
     *
     * 发消息时先锁它，是为了把「这对男女的免费条数」串起来：
     * 并发发两条、只剩 1 条免费时，第二个请求必须等第一个提交后再判断，否则两条都会免单。
     */
    public function lockByPair(int $a, int $b): ?Conversation
    {
        [$low, $high] = Conversation::pair($a, $b);

        return $this->model->newQuery()
            ->where('user_low_id', $low)
            ->where('user_high_id', $high)
            ->lockForUpdate()
            ->first();
    }

    public function createPair(int $lowId, int $highId, int $maleId, int $femaleId): Conversation
    {
        return $this->model->newQuery()->create([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
            'male_id' => $maleId,
            'female_id' => $femaleId,
        ]);
    }

    /**
     * 会话列表：我参与的、**我没删的**、**对方没被我拉黑/没拉黑我的**，按最后消息时间倒序。
     *
     * @param array<int,int> $excludeIds 任一方向拉黑了的对方 id
     * @return array<int,Conversation>
     */
    public function listForUser(int $userId, int $offset, int $limit, array $excludeIds = []): array
    {
        $rows = $this->queryForUser($userId, $excludeIds)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $rows->all();
    }

    public function countForUser(int $userId, array $excludeIds = []): int
    {
        return $this->queryForUser($userId, $excludeIds)->count();
    }

    /**
     * 会话未读总数（底部导航的角标用）。主动删掉或拉黑了的会话不再计数，
     * 不然角标会指向一个列表里看不到的会话。
     *
     * @param array<int,int> $excludeIds
     */
    public function unreadTotalFor(int $userId, array $excludeIds = []): int
    {
        $asLow = (int) $this->queryForUser($userId, $excludeIds)
            ->where('user_low_id', $userId)
            ->sum('low_unread');
        $asHigh = (int) $this->queryForUser($userId, $excludeIds)
            ->where('user_high_id', $userId)
            ->sum('high_unread');

        return $asLow + $asHigh;
    }

    /**
     * 留存清理之后修 `last_message_id`（docs/04 第 3 节）。
     *
     * 它可能正好指着刚被删掉的那条，不修的话会话列表会显示一条已经不存在的消息。
     * 一条 UPDATE 修完：**指向的那条没了就回退到当前最新的一条**，一条都不剩就是 null。
     *
     * @param array<int,int> $conversationIds
     */
    public function refreshLastMessageIds(array $conversationIds): void
    {
        if ($conversationIds === []) {
            return;
        }

        $this->model->newQuery()
            ->whereIn('id', $conversationIds)
            ->whereNotNull('last_message_id')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM cl_message m WHERE m.id = cl_conversation.last_message_id)')
            ->update([
                'last_message_id' => Db::raw(
                    '(SELECT MAX(m2.id) FROM cl_message m2 WHERE m2.conversation_id = cl_conversation.id)'
                ),
            ]);
    }

    /** 把我在这个会话里的未读清零（读会话 / 删会话时调用）。 */
    public function clearUnread(Conversation $conversation, int $userId): void
    {
        $column = $conversation->unreadColumnFor($userId);
        if ((int) $conversation->{$column} === 0) {
            return;
        }

        $conversation->{$column} = 0;
        $conversation->save();
    }

    /**
     * 删除会话 —— **只对自己隐藏**，对方那边照旧。
     *
     * 只是给自己的 `*_deleted_at` 盖个时间戳：对方再来消息时 `touchConversation` 会把它清空，
     * 会话就重新出现了（docs/01 P06）。
     */
    public function hideFor(Conversation $conversation, int $userId): void
    {
        $conversation->{$conversation->deletedAtColumnFor($userId)} = Carbon::now();
        $conversation->{$conversation->unreadColumnFor($userId)} = 0;
        $conversation->save();
    }

    /**
     * 我参与的会话的公共查询条件。
     *
     * 「我没删」在 SQL 里要分开写：我是 `low` 时看 `low_deleted_at`，是 `high` 时看 `high_deleted_at`，
     * 不能一个条件套两边。
     *
     * @param array<int,int> $excludeIds
     */
    private function queryForUser(int $userId, array $excludeIds): Builder
    {
        $query = $this->model->newQuery()
            ->where(function (Builder $inner) use ($userId): void {
                $inner->where('user_low_id', $userId)->orWhere('user_high_id', $userId);
            })
            ->where(function (Builder $inner) use ($userId): void {
                $inner->where('user_low_id', '!=', $userId)->orWhereNull('low_deleted_at');
            })
            ->where(function (Builder $inner) use ($userId): void {
                $inner->where('user_high_id', '!=', $userId)->orWhereNull('high_deleted_at');
            });

        if ($excludeIds !== []) {
            // 拉黑后互相不可见（docs/01 第 7 节）
            $query->whereNotIn('user_low_id', $excludeIds)->whereNotIn('user_high_id', $excludeIds);
        }

        return $query;
    }
}
