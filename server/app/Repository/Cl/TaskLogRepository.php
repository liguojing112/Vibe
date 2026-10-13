<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\TaskLog;
use App\Repository\IRepository;

/**
 * @extends IRepository<TaskLog>
 */
final class TaskLogRepository extends IRepository
{
    public function __construct(
        protected readonly TaskLog $model
    ) {}

    /** 这个周期领过没有（唯一索引兜底，这里只是先查一次给界面和判断用）。 */
    public function exists(int $userId, int $taskId, string $periodKey): bool
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('task_id', $taskId)
            ->where('period_key', $periodKey)
            ->exists();
    }
}
