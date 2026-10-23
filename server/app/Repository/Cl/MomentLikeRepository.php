<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\MomentLike;
use App\Repository\IRepository;

/**
 * @extends IRepository<MomentLike>
 */
final class MomentLikeRepository extends IRepository
{
    public function __construct(
        protected readonly MomentLike $model
    ) {}

    /**
     * 点赞，已经赞过就什么都不做。
     *
     * @return bool 这次**真的插入了**新行没有 —— 调用方靠它决定计数要不要 +1、要不要发通知
     */
    public function addIfNew(int $momentId, int $userId): bool
    {
        $now = date('Y-m-d H:i:s');

        return $this->model->newQuery()->insertOrIgnore([
            'moment_id' => $momentId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]) > 0;
    }

    /** @return bool 这次真的删掉了没有（没赞过就是 false，计数不要动） */
    public function remove(int $momentId, int $userId): bool
    {
        return $this->model->newQuery()
            ->where('moment_id', $momentId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    /**
     * 这批动态里我赞过哪些（列表一次查完，避免每条都查一次）。
     *
     * @param array<int,int> $momentIds
     * @return array<int,int>
     */
    public function likedAmong(int $userId, array $momentIds): array
    {
        if ($momentIds === []) {
            return [];
        }

        return array_map(
            static fn (mixed $id): int => (int) $id,
            $this->model->newQuery()
                ->where('user_id', $userId)
                ->whereIn('moment_id', $momentIds)
                ->pluck('moment_id')
                ->all(),
        );
    }

    /** 对账用：某条动态真实的点赞行数。 */
    public function countByMoment(int $momentId): int
    {
        return $this->model->newQuery()->where('moment_id', $momentId)->count();
    }
}
