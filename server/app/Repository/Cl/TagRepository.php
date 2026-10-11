<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Tag;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<Tag>
 */
final class TagRepository extends IRepository
{
    public function __construct(
        protected readonly Tag $model
    ) {}

    /**
     * 启用中的标签，按 sort 排序（App 启动配置、编辑资料页用）。
     */
    public function enabledList(): Collection
    {
        return $this->model->newQuery()->where('enabled', 1)->orderBy('sort')->get();
    }
}
