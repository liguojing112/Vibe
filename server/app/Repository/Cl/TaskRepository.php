<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Task;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<Task>
 */
final class TaskRepository extends IRepository
{
    public function __construct(
        protected readonly Task $model
    ) {}

    /**
     * 某个性别能做的任务（P11 的列表按这个铺）。
     *
     * `gender = 0` 是男女都适用，所以查询条件是「等于我 或 全部」。
     *
     * @return Collection<int,Task>
     */
    public function enabledForGender(int $gender): Collection
    {
        /** @var Collection<int,Task> $rows */
        $rows = $this->model->newQuery()
            ->where('enabled', 1)
            ->where(static function ($query) use ($gender): void {
                $query->where('gender', Task::GENDER_ALL)->orWhere('gender', $gender);
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $rows;
    }

    public function findEnabled(int $id): ?Task
    {
        /** @var null|Task $task */
        $task = $this->model->newQuery()
            ->whereKey($id)
            ->where('enabled', 1)
            ->first();

        return $task;
    }
}
