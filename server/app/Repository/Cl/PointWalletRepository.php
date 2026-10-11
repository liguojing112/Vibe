<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\PointWallet;
use App\Repository\IRepository;

/**
 * @extends IRepository<PointWallet>
 */
final class PointWalletRepository extends IRepository
{
    public function __construct(
        protected readonly PointWallet $model
    ) {}

    public function createForUser(int $userId): PointWallet
    {
        /** @var PointWallet $wallet */
        $wallet = $this->model->newQuery()->create(['user_id' => $userId]);
        return $wallet;
    }

    public function findByUserId(int $userId): ?PointWallet
    {
        return $this->model->newQuery()->where('user_id', $userId)->first();
    }

    /**
     * 取钱包并锁行（必须在事务里调用，改余额前一律走这里）。
     */
    public function lockByUserId(int $userId): ?PointWallet
    {
        return $this->model->newQuery()->where('user_id', $userId)->lockForUpdate()->first();
    }
}
