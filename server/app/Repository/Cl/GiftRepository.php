<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Gift;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<Gift>
 */
final class GiftRepository extends IRepository
{
    public function __construct(
        protected readonly Gift $model
    ) {}

    /**
     * 上架的礼物（P23/P31 的网格按这个顺序铺）。
     *
     * @return Collection<int,Gift>
     */
    public function enabledOrdered(): Collection
    {
        /** @var Collection<int,Gift> $rows */
        $rows = $this->model->newQuery()
            ->where('enabled', 1)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $rows;
    }

    public function findEnabled(int $id): ?Gift
    {
        /** @var null|Gift $gift */
        $gift = $this->model->newQuery()->whereKey($id)->where('enabled', 1)->first();

        return $gift;
    }
}
