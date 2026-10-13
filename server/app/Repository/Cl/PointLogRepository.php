<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\PointLog;
use App\Repository\IRepository;

/**
 * @extends IRepository<PointLog>
 */
final class PointLogRepository extends IRepository
{
    public function __construct(
        protected readonly PointLog $model
    ) {}

    public function existsByRef(string $type, string $refType, int $refId): bool
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->exists();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function add(array $data): void
    {
        $this->model->newQuery()->create($data);
    }

    /**
     * 某用户全部积分流水的 change / frozen_change 之和（对账用）。
     *
     * @return array{change: int, frozen_change: int}
     */
    public function sumByUser(int $userId): array
    {
        $row = $this->model->newQuery()
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(`change`), 0) AS change_sum, COALESCE(SUM(`frozen_change`), 0) AS frozen_sum')
            ->first();

        return [
            'change' => (int) ($row->change_sum ?? 0),
            'frozen_change' => (int) ($row->frozen_sum ?? 0),
        ];
    }

    /**
     * 某个用户的积分流水（P26 的收益明细），按时间倒序。
     *
     * 不过滤类型：P26 要求「私信收益、礼物收益、任务奖励、提现冻结、提现驳回退回、提现成功」都列出来，
     * 客户端按 `type` 挑图标和文案。
     *
     * @return array<int,array<string,mixed>>
     */
    public function pageForUser(int $userId, int $offset, int $limit): array
    {
        $rows = $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'title' => (string) $row->title,
                'change' => (int) $row->change,
                'frozen_change' => (int) $row->frozen_change,
                'balance_after' => (int) $row->balance_after,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return $list;
    }

    public function countForUser(int $userId): int
    {
        return $this->model->newQuery()->where('user_id', $userId)->count();
    }

    /**
     * 一段时间内**收益类**积分的合计（P26 的「今日收益积分」）。
     *
     * 只算私信和礼物分成，和 `cl_user.total_earned_points` 一个口径
     * （`docs/06` 第 5 节：任务奖励不算累计收益），所以任务奖励不算「今日收益」。
     *
     * @param array<int,string> $types
     */
    public function sumChangeBetween(int $userId, array $types, string $from, string $to): int
    {
        if ($types === []) {
            return 0;
        }

        return (int) $this->model->newQuery()
            ->where('user_id', $userId)
            ->whereIn('type', $types)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->sum('change');
    }
}
