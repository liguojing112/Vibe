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
}
