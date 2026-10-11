<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\LevelConfig;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<LevelConfig>
 */
final class LevelConfigRepository extends IRepository
{
    public function __construct(
        protected readonly LevelConfig $model
    ) {}

    public function allOrdered(): Collection
    {
        return $this->model->newQuery()->orderBy('level')->get();
    }

    public function findByLevel(int $level): ?LevelConfig
    {
        return $this->model->newQuery()->where('level', $level)->first();
    }
}
