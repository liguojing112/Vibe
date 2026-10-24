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

    /**
     * 按名字取图标 —— 礼物消息只存了礼物名，渲染聊天气泡时用它补图标。
     */
    public function iconByName(string $name): string
    {
        return (string) $this->model->newQuery()->where('name', $name)->value('icon');
    }

    public function findEnabled(int $id): ?Gift
    {
        /** @var null|Gift $gift */
        $gift = $this->model->newQuery()->whereKey($id)->where('enabled', 1)->first();

        return $gift;
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
