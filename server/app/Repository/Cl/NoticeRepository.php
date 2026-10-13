<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Notice;
use App\Repository\IRepository;

/**
 * @extends IRepository<Notice>
 */
final class NoticeRepository extends IRepository
{
    public function __construct(
        protected readonly Notice $model
    ) {}

    /**
     * 某个用户某分类下的通知（P10 的两个 Tab）。
     *
     * 可见的 = **发给我的**（`user_id` = 我）+ **全员公告**（`user_id` = 0）。
     *
     * @return array<int,Notice>
     */
    public function pageForUser(int $userId, int $category, int $offset, int $limit): array
    {
        /** @var array<int,Notice> $rows */
        $rows = $this->model->newQuery()
            ->whereIn('user_id', [$userId, Notice::ALL_USERS])
            ->where('category', $category)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    public function countForUser(int $userId, int $category): int
    {
        return $this->model->newQuery()
            ->whereIn('user_id', [$userId, Notice::ALL_USERS])
            ->where('category', $category)
            ->count();
    }
}
