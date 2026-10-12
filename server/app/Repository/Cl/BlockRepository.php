<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Block;
use App\Repository\IRepository;

/**
 * @extends IRepository<Block>
 */
final class BlockRepository extends IRepository
{
    public function __construct(
        protected readonly Block $model
    ) {}

    public function exists(int $userId, int $targetId): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('target_id', $targetId)
            ->exists();
    }

    public function add(int $userId, int $targetId): void
    {
        if ($this->exists($userId, $targetId)) {
            return;
        }
        $this->model->newQuery()->insert([
            'user_id' => $userId,
            'target_id' => $targetId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function remove(int $userId, int $targetId): void
    {
        $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('target_id', $targetId)
            ->delete();
    }

    /**
     * 两个人之间有没有任一方向的拉黑（拉黑后双方互相不可见）。
     */
    public function blockedEitherWay(int $userId, int $targetId): bool
    {
        return $this->model->newQuery()
            ->where(function ($query) use ($userId, $targetId): void {
                $query->where('user_id', $userId)->where('target_id', $targetId);
            })
            ->orWhere(function ($query) use ($userId, $targetId): void {
                $query->where('user_id', $targetId)->where('target_id', $userId);
            })
            ->exists();
    }

    /**
     * 一个用户相关的、任一方向拉黑了的对方 id（列表过滤用）。
     *
     * @return array<int,int>
     */
    public function relatedBlockedIds(int $userId): array
    {
        $mine = $this->model->newQuery()->where('user_id', $userId)->pluck('target_id')->all();
        $theirs = $this->model->newQuery()->where('target_id', $userId)->pluck('user_id')->all();

        $ids = array_map(static fn (mixed $id): int => (int) $id, array_merge($mine, $theirs));

        return array_values(array_unique($ids));
    }

    /**
     * 我拉黑的人（黑名单列表）。
     *
     * @return array<int,int>
     */
    public function blockedIds(int $userId, int $offset, int $limit): array
    {
        $values = $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->pluck('target_id')
            ->all();

        return array_map(static fn (mixed $id): int => (int) $id, array_values($values));
    }

    public function countBlocked(int $userId): int
    {
        return $this->model->newQuery()->where('user_id', $userId)->count();
    }
}
