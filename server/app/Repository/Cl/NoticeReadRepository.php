<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\NoticeRead;
use App\Repository\IRepository;

/**
 * 全员公告的已读记录（docs/04 第 6 节）。
 *
 * @extends IRepository<NoticeRead>
 */
final class NoticeReadRepository extends IRepository
{
    public function __construct(
        protected readonly NoticeRead $model
    ) {}

    /**
     * 批量补「我读过了」的记录（唯一索引 `(notice_id, user_id)` 兜底重复）。
     *
     * @param array<int,int> $noticeIds
     */
    public function markRead(int $userId, array $noticeIds): void
    {
        if ($noticeIds === []) {
            return;
        }

        $rows = [];
        $now = date('Y-m-d H:i:s');
        foreach ($noticeIds as $noticeId) {
            $rows[] = ['notice_id' => $noticeId, 'user_id' => $userId, 'created_at' => $now, 'updated_at' => $now];
        }

        // 并发/重复调用会撞唯一索引，`insertOrIgnore` 直接跳过
        $this->model->newQuery()->insertOrIgnore($rows);
    }

    /**
     * 这批公告里我读过哪些（列表要按它给公告标「已读」）。
     *
     * @param array<int,int> $noticeIds
     * @return array<int,int>
     */
    public function readNoticeIds(int $userId, array $noticeIds): array
    {
        if ($noticeIds === []) {
            return [];
        }

        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereIn('notice_id', $noticeIds)
            ->pluck('notice_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
