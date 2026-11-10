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

    /**
     * 某条规则下参与中的目标女号，**最久没说过话的排在前面**。
     *
     * 定时任务每轮只看一部分目标（防止一次跑太久）。如果按 id 固定顺序取，
     * 排在前面的永远被看到、后面的永远轮不到。按 `last_sent_at` 排就能自然轮转：
     * 从没发过的（NULL）最优先，发过的按时间先后。
     *
     * @return array<int,BotChatTarget>
     */
    public function listActiveByRuleDueFirst(int $ruleId, int $limit): array
    {
        /** @var array<int,BotChatTarget> $rows */
        $rows = $this->model->newQuery()
            ->where('rule_id', $ruleId)
            ->where('enabled', 1)
            ->orderByRaw('last_sent_at IS NULL DESC')
            ->orderBy('last_sent_at')
            ->orderBy('id')
            ->limit($limit)
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
     * 累加「这个女号今天总共收到多少」——**只用于后台展示**，不做任何限流判断。
     *
     * 15 元/天的上限是按「男号 + 女号」这一对算的，存在 `cl_bot_chat_pair` 上；
     * 同一个女号被多个男号聊时，这里的数字会超过 15 元，是正常的。
     */
    public function addPoints(int $targetId, int $points, string $sentAt): void
    {
        $this->model->newQuery()->whereKey($targetId)->update([
            'virtual_points_today' => Db::raw('virtual_points_today + ' . $points),
            'rounds_today' => Db::raw('rounds_today + 1'),
            'last_sent_at' => $sentAt,
        ]);
    }

    /** 只刷最后发送时间（女 AI 回复时用，不重复计收益）。 */
    public function touchLastSent(int $targetId, string $sentAt): void
    {
        $this->model->newQuery()->whereKey($targetId)->update(['last_sent_at' => $sentAt]);
    }
}
