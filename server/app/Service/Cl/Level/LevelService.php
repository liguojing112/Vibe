<?php

declare(strict_types=1);

namespace App\Service\Cl\Level;

use App\Model\Cl\LevelConfig;
use App\Model\Cl\User;
use App\Repository\Cl\LevelConfigRepository;

/**
 * 女用户等级（docs/06 第 5 节）。
 *
 * **只升不降**，一次可以跨好几级（取「门槛已满足」里最高的那级）；后台锁了等级就不自动升。
 */
final class LevelService
{
    public function __construct(
        private readonly LevelConfigRepository $levelConfigRepository,
    ) {}

    /**
     * 按 `total_earned_points` 检查升级（私信/礼物分成之后调）。
     * 返回升到的那个等级；没升级返回 null。
     *
     * 注：**升级系统通知**要等通知表（P6）建好再接，这里先只改等级。
     */
    public function checkUpgrade(User $female): ?LevelConfig
    {
        if ($female->gender !== User::GENDER_FEMALE || $female->level_locked === 1) {
            return null;
        }

        $earned = (int) $female->total_earned_points;
        // 门槛是升序的，取最后一个满足的就是「能升到的最高级」
        $target = $this->levelConfigRepository->allOrdered()
            ->filter(static fn (LevelConfig $level): bool => $level->threshold_points <= $earned)
            ->last();

        if (! $target instanceof LevelConfig || $target->level <= $female->level) {
            return null;
        }

        $female->update(['level' => $target->level]);

        return $target;
    }
}
