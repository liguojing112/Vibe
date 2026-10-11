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
}
