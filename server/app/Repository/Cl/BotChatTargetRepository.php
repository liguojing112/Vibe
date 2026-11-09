<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\BotChatTarget;
use App\Repository\IRepository;
use Hyperf\DbConnection\Db;

/**
 * @extends IRepository<BotChatTarget>
 */
final class BotChatTargetRepository extends IRepository
{
    public function __construct(
        protected readonly BotChatTarget $model
    ) {}

    /**
     * 某条规则下参与中的目标女号。
     *
     * @return array<int,BotChatTarget>
     */
    public function listActiveByRule(int $ruleId): array
    {
        /** @var array<int,BotChatTarget> $rows */
        $rows = $this->model->newQuery()
            ->where('rule_id', $ruleId)
            ->where('enabled', 1)
            ->orderBy('id')
            ->get()
            ->all();

        return $rows;
    }

    public function findByRuleAndFemale(int $ruleId, int $femaleId): ?BotChatTarget
    {
        /** @var null|BotChatTarget $row */
        $row = $this->model->newQuery()
            ->where('rule_id', $ruleId)
            ->where('female_id', $femaleId)
            ->first();

        return $row;
    }

    /**
     * 跨天重置：把不属于今天的计数清零。
     *
     * 读的时候顺手做，不另开定时任务 —— 和 `AiPersonaRepository::resetDailyIfStale` 一致。
     */
    public function resetDailyIfStale(int $targetId, string $today): void
    {
        $this->model->newQuery()
            ->whereKey($targetId)
            ->where(function ($q) use ($today): void {
                $q->whereNull('stat_date')->orWhere('stat_date', '!=', $today);
            })
            ->update([
                'stat_date' => $today,
                'virtual_points_today' => 0,
                'rounds_today' => 0,
                'stopped_reason' => null,
            ]);
    }

    /**
     * 累加虚拟收益 + 今日轮数。
     *
     * 达到上限后写 `stopped_reason`：**只计数，不动钱包**。
     */
    public function addVirtualPoints(int $targetId, int $points, int $limitPoints, string $sentAt): void
    {
        $this->model->newQuery()->whereKey($targetId)->update([
            'virtual_points_today' => Db::raw('virtual_points_today + ' . $points),
            'rounds_today' => Db::raw('rounds_today + 1'),
            'last_sent_at' => $sentAt,
        ]);

        // 单独判一次上限：SQL 里写 CASE 更难读，而且这一步只在真正达到时才更新
        $row = $this->findById($targetId);
        if ($row instanceof BotChatTarget && (int) $row->virtual_points_today >= $limitPoints) {
            $this->model->newQuery()->whereKey($targetId)->update([
                'stopped_reason' => BotChatTarget::STOP_DAILY_LIMIT,
            ]);
        }
    }

    /** 只刷最后发送时间（女 AI 回复时用，不重复计收益）。 */
    public function touchLastSent(int $targetId, string $sentAt): void
    {
        $this->model->newQuery()->whereKey($targetId)->update(['last_sent_at' => $sentAt]);
    }
}
