<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\CoinLog;
use App\Repository\IRepository;

/**
 * @extends IRepository<CoinLog>
 */
final class CoinLogRepository extends IRepository
{
    public function __construct(
        protected readonly CoinLog $model
    ) {}

    /**
     * 同一笔业务是否已经记过账（幂等用，见 04 文档 cl_coin_log 的唯一索引）。
     */
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
     * 某用户全部金币流水的 change 之和（对账用，见 docs/06 第 10 节场景 34）。
     */
    public function sumChangeByUser(int $userId): int
    {
        return (int) $this->model->newQuery()->where('user_id', $userId)->sum('change');
    }
}
