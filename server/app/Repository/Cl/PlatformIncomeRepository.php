<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\PlatformIncome;
use App\Repository\IRepository;

/**
 * @extends IRepository<PlatformIncome>
 */
final class PlatformIncomeRepository extends IRepository
{
    public function __construct(
        protected readonly PlatformIncome $model
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function add(array $data): void
    {
        $this->model->newQuery()->create($data);
    }
}
