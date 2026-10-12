<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Follow;
use App\Repository\IRepository;

/**
 * @extends IRepository<Follow>
 */
final class FollowRepository extends IRepository
{
    /** 普通查询里的被关注者列。 */
    private const COLUMN_TARGET = 'target_id';

    /** 互关连表（`cl_follow as a`）里的被关注者列，不写别名会和 b 表撞。 */
    private const COLUMN_JOINED_TARGET = 'a.target_id';

    public function __construct(
        protected readonly Follow $model
    ) {}

    public function exists(int $userId, int $targetId): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('target_id', $targetId)
            ->exists();
    }

    /** 关注（已关注就不动，避免重复插入撞唯一索引）。 */
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
     * @param array<int,int> $exclude 要排除的人（拉黑关系，双向都算）
     */
    public function countFollowing(int $userId, array $exclude = []): int
    {
        $query = $this->model->newQuery()->where('user_id', $userId);
        $this->excludeTargets($query, self::COLUMN_TARGET, $exclude);

        return $query->count();
    }

    /**
     * @param array<int,int> $exclude
     */
    public function countFollowers(int $targetId, array $exclude = []): int
    {
        $query = $this->model->newQuery()->where('target_id', $targetId);
        if ($exclude !== []) {
            $query->whereNotIn('user_id', $exclude);
        }

        return $query->count();
    }

    /**
     * @param array<int,int> $exclude
     */
    public function countFriends(int $userId, array $exclude = []): int
    {
        $query = $this->mutualQuery($userId);
        $this->excludeTargets($query, self::COLUMN_JOINED_TARGET, $exclude);

        return $query->count();
    }

    /**
     * 我关注的人（新关注的在前）。
     *
     * @return array<int,int>
     */
    public function followingIds(int $userId, array $exclude, int $offset, int $limit): array
    {
        $query = $this->model->newQuery()->where('user_id', $userId);
        $this->excludeTargets($query, self::COLUMN_TARGET, $exclude);

        return $this->ids($query->orderByDesc('id')->offset($offset)->limit($limit)->pluck('target_id')->all());
    }

    /**
     * 关注我的人。
     *
     * @return array<int,int>
     */
    public function followerIds(int $targetId, array $exclude, int $offset, int $limit): array
    {
        $query = $this->model->newQuery()->where('target_id', $targetId);
        if ($exclude !== []) {
            $query->whereNotIn('user_id', $exclude);
        }

        return $this->ids($query->orderByDesc('id')->offset($offset)->limit($limit)->pluck('user_id')->all());
    }

    /**
     * 互关（好友）：我关注了 ta，ta 也关注了我。
     *
     * @return array<int,int>
     */
    public function friendIds(int $userId, array $exclude, int $offset, int $limit): array
    {
        $query = $this->mutualQuery($userId);
        $this->excludeTargets($query, self::COLUMN_JOINED_TARGET, $exclude);

        return $this->ids(
            $query->orderByDesc('a.id')->offset($offset)->limit($limit)->pluck('a.target_id')->all(),
        );
    }

    /**
     * 互关（双向关注）的连表查询。
     */
    private function mutualQuery(int $userId)
    {
        return $this->model->newQuery()
            ->from('cl_follow as a')
            ->join('cl_follow as b', function ($join): void {
                $join->on('b.user_id', '=', 'a.target_id')
                    ->on('b.target_id', '=', 'a.user_id');
            })
            ->where('a.user_id', $userId);
    }

    /**
     * @param array<int,int> $exclude
     */
    private function excludeTargets($query, string $column, array $exclude): void
    {
        if ($exclude !== []) {
            $query->whereNotIn($column, $exclude);
        }
    }

    /**
     * 这批人里我关注了谁（列表里标 `is_following`；两个方向都有就是互关）。
     *
     * @param array<int,int> $targetIds
     * @return array<int,int>
     */
    public function followingAmong(int $userId, array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return $this->ids(
            $this->model->newQuery()
                ->where('user_id', $userId)
                ->whereIn('target_id', $targetIds)
                ->pluck('target_id')
                ->all(),
        );
    }

    /**
     * 这批人里谁关注了我（配合 [followingAmong] 判互关）。
     *
     * @param array<int,int> $userIds
     * @return array<int,int>
     */
    public function followedByAmong(int $userId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->ids(
            $this->model->newQuery()
                ->where('target_id', $userId)
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->all(),
        );
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,int>
     */
    private function ids(array $values): array
    {
        return array_map(static fn (mixed $id): int => (int) $id, array_values($values));
    }
}
