<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\City;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<City>
 */
final class CityRepository extends IRepository
{
    public function __construct(
        protected readonly City $model
    ) {}

    /**
     * 启用中的热门城市，按 sort 排序（App 启动配置、筛选页 Chip 用）。
     */
    public function enabledList(): Collection
    {
        return $this->model->newQuery()->where('enabled', 1)->orderBy('sort')->get();
    }
}
