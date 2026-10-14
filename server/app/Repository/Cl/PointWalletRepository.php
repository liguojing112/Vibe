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

    /**
     * 批量取钱包（后台列表要一次把整页用户的余额带出来，别 N+1）。
     *
     * @param array<int,int> $userIds
     * @return array<int,PointWallet> userId => 钱包
     */
    public function listByUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $map = [];
        foreach ($this->model->newQuery()->whereIn('user_id', $userIds)->get() as $wallet) {
            $map[(int) $wallet->user_id] = $wallet;
        }

        return $map;
    }
}
