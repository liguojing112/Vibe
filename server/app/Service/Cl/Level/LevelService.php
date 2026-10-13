<?php

declare(strict_types=1);

namespace App\Service\Cl\Level;

use App\Model\Cl\LevelConfig;
use App\Model\Cl\Notice;
use App\Model\Cl\User;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Notice\NoticeService;

/**
 * 女用户等级（docs/06 第 5 节）。
 *
 * **只升不降**，一次可以跨好几级（取「门槛已满足」里最高的那级）；后台锁了等级就不自动升。
 *
 * 升级时给女方发一条系统通知。通知**和等级写在同一个事务里**（这个方法是发消息/送礼那套事务里调的），
 * 所以不会出现「等级升上去了、通知却没发出来」。
 */
final class LevelService
{
    public function __construct(
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly NoticeService $noticeService,
    ) {}

    /**
     * 按 `total_earned_points` 检查升级（私信/礼物分成之后调）。
     * 返回升到的那个等级；没升级返回 null。
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

        $this->noticeService->send(
            $female->id,
            Notice::CATEGORY_SYSTEM,
            '等级提升',
            sprintf('恭喜你升到 %s，私信单价和分成比例已按新等级生效', $target->name),
            Notice::BIZ_LEVEL_UP,
            (int) $target->level,
        );

        return $target;
    }
}
