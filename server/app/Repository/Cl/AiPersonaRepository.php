<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\AiPersona;
use App\Repository\IRepository;

/**
 * @extends IRepository<AiPersona>
 */
final class AiPersonaRepository extends IRepository
{
    public function __construct(
        protected readonly AiPersona $model
    ) {}

    public function findByUser(int $userId): ?AiPersona
    {
        /** @var null|AiPersona $row */
        $row = $this->model->newQuery()->where('user_id', $userId)->first();

        return $row;
    }

    /**
     * 开启了主动搭话、并且处于正常状态的人设。
     *
     * @return array<int,AiPersona>
     */
    public function listProactive(int $limit = 50): array
    {
        /** @var array<int,AiPersona> $rows */
        $rows = $this->model->newQuery()
            ->where('enabled', 1)
            ->where('status', AiPersona::STATUS_NORMAL)
            ->where('proactive', '>', AiPersona::PROACTIVE_OFF)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    /** 记一次调用：条数 +1、累计 token。 */
    public function recordUsage(int $personaId, int $promptTokens, int $completionTokens, string $today): void
    {
        $this->model->newQuery()->whereKey($personaId)->update([
            'replied_today' => \Hyperf\DbConnection\Db::raw('replied_today + 1'),
            'replied_on' => $today,
            'prompt_tokens' => \Hyperf\DbConnection\Db::raw('prompt_tokens + ' . $promptTokens),
            'completion_tokens' => \Hyperf\DbConnection\Db::raw('completion_tokens + ' . $completionTokens),
        ]);
    }

    /** 跨天了就把今日计数清零（读的时候顺手判断，不另开定时任务）。 */
    public function resetDailyIfStale(int $personaId, string $today): void
    {
        $this->model->newQuery()
            ->whereKey($personaId)
            ->where(function ($q) use ($today): void {
                $q->whereNull('replied_on')->orWhere('replied_on', '!=', $today);
            })
            ->update(['replied_today' => 0, 'replied_on' => $today]);
    }
}
