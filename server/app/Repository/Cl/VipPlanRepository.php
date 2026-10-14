<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\VipPlan;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<VipPlan>
 */
final class VipPlanRepository extends IRepository
{
    public function __construct(
        protected readonly VipPlan $model
    ) {}

    /**
     * 上架的套餐（P12 的价格卡按这个顺序铺）。
     *
     * @return Collection<int,VipPlan>
     */
    public function enabledOrdered(): Collection
    {
        /** @var Collection<int,VipPlan> $rows */
        $rows = $this->model->newQuery()
            ->where('enabled', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $rows;
    }

    public function findEnabled(int $id): ?VipPlan
    {
        /** @var null|VipPlan $plan */
        $plan = $this->model->newQuery()->whereKey($id)->where('enabled', 1)->first();

        return $plan;
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
