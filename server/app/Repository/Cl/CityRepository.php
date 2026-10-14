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

    /**
     * 后台列表：**不分页**（这些表就几行到几十行），按 sort 排。
     *
     * 和 App 侧的 `enabledOrdered()` 不同：后台要看**停用的**那些，不然关掉之后就没法再打开。
     *
     * @return array<int,array<string,mixed>>
     */
    public function adminList(): array
    {
        $list = [];
        foreach ($this->model->newQuery()->orderBy('sort')->orderBy('id')->get() as $row) {
            $list[] = $row->toArray();
        }

        return $list;
    }

    /** @param array<int,int> $ids */
    public function removeByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->model->newQuery()->whereIn('id', $ids)->delete();
    }

}
