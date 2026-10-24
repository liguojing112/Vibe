<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\AiReplyLog;
use App\Repository\IRepository;

/**
 * @extends IRepository<AiReplyLog>
 */
final class AiReplyLogRepository extends IRepository
{
    public function __construct(
        protected readonly AiReplyLog $model
    ) {}

    /**
     * @param array<string,mixed> $data
     */
    public function createLog(array $data): AiReplyLog
    {
        return $this->model->newQuery()->create($data);
    }

    /** 今日某个人设调用了几次（后台列表用）。 */
    public function countToday(int $personaId, string $from): int
    {
        return $this->model->newQuery()
            ->where('persona_id', $personaId)
            ->where('created_at', '>=', $from)
            ->count();
    }
}
