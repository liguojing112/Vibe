<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\CoinWallet;
use App\Repository\IRepository;

/**
 * @extends IRepository<CoinWallet>
 */
final class CoinWalletRepository extends IRepository
{
    public function __construct(
        protected readonly CoinWallet $model
    ) {}

    public function createForUser(int $userId): CoinWallet
    {
        /** @var CoinWallet $wallet */
        $wallet = $this->model->newQuery()->create(['user_id' => $userId]);
        return $wallet;
    }

    public function findByUserId(int $userId): ?CoinWallet
    {
        return $this->model->newQuery()->where('user_id', $userId)->first();
    }
}
