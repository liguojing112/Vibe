<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\User;
use App\Repository\IRepository;

/**
 * @extends IRepository<User>
 */
final class UserRepository extends IRepository
{
    public function __construct(
        protected readonly User $model
    ) {}

    public function findByAccount(string $account): ?User
    {
        return $this->model->newQuery()->where('account', $account)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->model->newQuery()->where('phone', $phone)->first();
    }

    /**
     * 登录用：账号或手机号都能登录（docs/05 第 4.1 节）。
     */
    public function findByAccountOrPhone(string $value): ?User
    {
        return $this->model->newQuery()
            ->where('account', $value)
            ->orWhere('phone', $value)
            ->first();
    }

    /**
     * @return null|User
     */
    public function findById(mixed $id): mixed
    {
        return $this->model->newQuery()->whereKey($id)->first();
    }
}
