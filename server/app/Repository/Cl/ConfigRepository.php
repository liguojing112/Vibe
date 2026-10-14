<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Config;
use App\Repository\IRepository;

/**
 * @extends IRepository<Config>
 */
final class ConfigRepository extends IRepository
{
    public function __construct(
        protected readonly Config $model
    ) {}

    public function findByKey(string $key): ?Config
    {
        return $this->model->newQuery()->where('key', $key)->first();
    }

    /**
     * 某个分组的全部配置项（后台按分组铺表单）。
     *
     * @return \Hyperf\Database\Model\Collection<int,Config>
     */
    public function listByGroup(string $group): \Hyperf\Database\Model\Collection
    {
        return $this->model->newQuery()->where('group', $group)->orderBy('key')->get();
    }

}
