<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\WithdrawAccount;
use App\Repository\IRepository;

/**
 * @extends IRepository<WithdrawAccount>
 */
final class WithdrawAccountRepository extends IRepository
{
    public function __construct(
        protected readonly WithdrawAccount $model
    ) {}

    public function findByUserId(int $userId): ?WithdrawAccount
    {
        /** @var null|WithdrawAccount $row */
        $row = $this->model->newQuery()->where('user_id', $userId)->first();

        return $row;
    }
}
