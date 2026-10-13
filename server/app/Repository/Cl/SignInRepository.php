<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\SignIn;
use App\Repository\IRepository;

/**
 * @extends IRepository<SignIn>
 */
final class SignInRepository extends IRepository
{
    public function __construct(
        protected readonly SignIn $model
    ) {}

    /** 这天签过没有（`sign_date` 是日期，不含时间）。 */
    public function existsOn(int $userId, string $date): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('sign_date', $date)
            ->exists();
    }
}
