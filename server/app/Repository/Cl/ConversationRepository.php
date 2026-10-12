<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Conversation;
use App\Repository\IRepository;

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
     * 我参与的所有会话（会话列表用，按最后消息时间倒序）。
     *
     * @return array<int,Conversation>
     */
    public function listForUser(int $userId, int $offset, int $limit): array
    {
        $rows = $this->model->newQuery()
            ->where(function ($query) use ($userId): void {
                $query->where('user_low_id', $userId)->orWhere('user_high_id', $userId);
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return $rows->all();
    }

    public function countForUser(int $userId): int
    {
        return $this->model->newQuery()
            ->where(function ($query) use ($userId): void {
                $query->where('user_low_id', $userId)->orWhere('user_high_id', $userId);
            })
            ->count();
    }
}
