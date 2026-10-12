<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Visit;
use App\Repository\IRepository;
use Hyperf\Carbon\Carbon;

/**
 * @extends IRepository<Visit>
 */
final class VisitRepository extends IRepository
{
    public function __construct(
        protected readonly Visit $model
    ) {}

    /**
     * 记一条访客：**同一人同一天只留一条**，重复访问只更新 `last_visit_at`（docs/04 第 2 节）。
     */
    public function record(int $visitorId, int $targetId): void
    {
        $now = Carbon::now();
        $today = $now->toDateString();

        $this->model->newQuery()->upsert(
            [[
                'visitor_id' => $visitorId,
                'target_id' => $targetId,
                'visit_date' => $today,
                'last_visit_at' => $now->toDateTimeString(),
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]],
            ['visitor_id', 'target_id', 'visit_date'],
            ['last_visit_at', 'updated_at'],
        );
    }

    /**
     * 访问过我的人，**一个人一行**（同一个人多天访问只显示最近一次），最近访问的在前。
     *
     * @return array<int,string> visitor_id => 最近访问时间
     */
    public function recentVisitors(int $targetId, array $exclude, int $offset, int $limit): array
    {
        $query = $this->model->newQuery()
            ->selectRaw('visitor_id, MAX(last_visit_at) as last_at')
            ->where('target_id', $targetId);
        if ($exclude !== []) {
            $query->whereNotIn('visitor_id', $exclude);
        }

        $rows = $query
            ->groupBy('visitor_id')
            ->orderByDesc('last_at')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->visitor_id] = (string) $row->last_at;
        }

        return $result;
    }

    /** 访问过我的人数（去重，P08 的「有 N 人看过你的主页」）。 */
    public function countDistinctVisitors(int $targetId, array $exclude = []): int
    {
        $query = $this->model->newQuery()->where('target_id', $targetId);
        if ($exclude !== []) {
            $query->whereNotIn('visitor_id', $exclude);
        }

        return $query->distinct()->count('visitor_id');
    }

    /** 未读访客数：`read_at` 为空的条数（`/user/me` 的统计、底部角标用）。 */
    public function countUnread(int $targetId): int
    {
        return $this->model->newQuery()
            ->where('target_id', $targetId)
            ->whereNull('read_at')
            ->count();
    }

    /** 打开访客列表后把未读清掉（docs/05 第 4.4 节）。 */
    public function markAllRead(int $targetId): void
    {
        $this->model->newQuery()
            ->where('target_id', $targetId)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()->toDateTimeString()]);
    }
}
