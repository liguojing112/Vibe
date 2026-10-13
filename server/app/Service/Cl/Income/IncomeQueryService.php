<?php

declare(strict_types=1);

namespace App\Service\Cl\Income;

use App\Exception\ClBusinessException;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\PointLogType;
use App\Repository\Cl\GiftRecordRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\PointLogRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;

/**
 * 女用户的收益与等级（docs/05 第 4.8 节，P26/P27）。
 *
 * 「改钱」在 `PointWalletService`，这里只读不写。
 *
 * 分成比例、单价、门槛**一律现算**（`cl_level_config` + 配置中心），不在代码里写死 ——
 * 后台把 Lv1 分成从 3000 改成 3500，P27 上立刻跟着变。
 */
final class IncomeQueryService
{
    /** P26 每页条数（docs/05 第 1 节分页约定 20）。 */
    public const PAGE_SIZE = 20;

    /** P26 底部说明（docs/01 P26 的原文）。 */
    private const NOTICE = '积分由男用户付费消息和礼物按等级比例分成获得';

    /**
     * 「今日收益积分」只算这两类，和 `cl_user.total_earned_points` 一个口径
     * （`docs/06` 第 5 节：任务奖励不算累计收益，所以也不算「收益」）。
     */
    private const INCOME_TYPES = [
        PointLogType::MESSAGE_INCOME->value,
        PointLogType::GIFT_INCOME->value,
    ];

    public function __construct(
        private readonly PointWalletService $pointWalletService,
        private readonly PointLogRepository $pointLogRepository,
        private readonly MessageRepository $messageRepository,
        private readonly GiftRecordRepository $giftRecordRepository,
        private readonly LevelConfigRepository $levelConfigRepository,
        private readonly MessagePricingService $pricingService,
        private readonly AppConfigService $configService,
    ) {}

    /**
     * `/income/summary`：可用积分、冻结积分、约合人民币、今日三项（P26）。
     *
     * @return array<string,mixed>
     */
    public function summary(User $user): array
    {
        $this->assertFemale($user);

        $wallet = $this->pointWalletService->summaryOf($user->id);
        $balance = (int) $wallet['balance'];
        [$from, $to] = $this->today();

        return [
            'balance' => $balance,
            'frozen' => (int) $wallet['frozen'],
            // 约合人民币给的是**分**，客户端拿 MoneyFormat 直接显示「¥11.62」（铁律 6：换算在服务端）
            'yuan_fen' => $this->yuanFen($balance),
            'today_points' => $this->pointLogRepository->sumChangeBetween($user->id, self::INCOME_TYPES, $from, $to),
            'today_messages' => $this->messageRepository->countReceivedBetween($user->id, $from, $to),
            'today_gifts' => $this->giftRecordRepository->countReceivedBetween($user->id, $from, $to),
            'notice' => self::NOTICE,
        ];
    }

    /**
     * `/income/logs`：积分流水（P26 的收益明细）。不过滤类型 —— 收益、提现都在里面。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function logs(User $user, int $page): array
    {
        $this->assertFemale($user);

        return [
            'list' => $this->pointLogRepository->pageForUser(
                $user->id,
                max(0, ($page - 1) * self::PAGE_SIZE),
                self::PAGE_SIZE,
            ),
            'total' => $this->pointLogRepository->countForUser($user->id),
            'page' => $page,
        ];
    }

    /**
     * `/income/level`：当前等级、累计收益、下一级门槛与进度、我的单价与分成、全部等级表（P27）。
     *
     * @return array<string,mixed>
     */
    public function level(User $user): array
    {
        $this->assertFemale($user);

        $current = $this->levelOf($user);
        $next = $this->nextLevelOf($current);
        $earned = (int) $user->total_earned_points;
        $realNamePassed = $user->real_name_status === User::REAL_NAME_PASSED;

        // 等级表里的分成（不打折）和「我实际能拿的」（未实名要打折）是两回事，P27 两个都要显示
        $levelRate = (int) $current->message_share_rate;
        $actualRate = $this->pricingService->shareRateOf($user, $levelRate);

        $data = [
            'level' => (int) $current->level,
            'name' => (string) $current->name,
            'total_earned_points' => $earned,
            'level_locked' => $user->level_locked === 1,
            'next_level' => $next?->level,
            'next_threshold' => $next === null ? null : (int) $next->threshold_points,
            'points_to_next' => $next === null ? 0 : max(0, (int) $next->threshold_points - $earned),
            'progress_percent' => $this->progressPercent($earned, $current, $next),
            // 我的收费标准（只读）
            'message_price' => (int) $current->message_price,
            'share_rate' => $levelRate,
            'actual_share_rate' => $actualRate,
            'gift_share_rate' => $this->pricingService->giftShareRateOf($user),
            'real_name_passed' => $realNamePassed,
            'call_enabled' => (bool) $this->configService->get('feature.call_enabled', false),
            'voice_price' => (int) $current->voice_price,
            'video_price' => (int) $current->video_price,
            'levels' => $this->levelTable(),
        ];

        return $data;
    }

    /**
     * 全部等级表（P27 的「Lv1~Lv5 的升级门槛和分成比例」）。
     *
     * @return array<int,array<string,mixed>>
     */
    private function levelTable(): array
    {
        $rows = [];
        foreach ($this->levelConfigRepository->allOrdered() as $level) {
            $rows[] = [
                'level' => (int) $level->level,
                'name' => (string) $level->name,
                'threshold_points' => (int) $level->threshold_points,
                'message_price' => (int) $level->message_price,
                'message_share_rate' => (int) $level->message_share_rate,
            ];
        }

        return $rows;
    }

    /** 本级门槛 → 下一级门槛之间的进度百分比（没有下一级就是 100）。 */
    private function progressPercent(int $earned, LevelConfig $current, ?LevelConfig $next): int
    {
        if ($next === null) {
            return 100;
        }

        $floor = (int) $current->threshold_points;
        $span = (int) $next->threshold_points - $floor;
        if ($span <= 0) {
            return 100;
        }

        return max(0, min(100, intdiv(($earned - $floor) * 100, $span)));
    }

    /**
     * 我当前等级的配置。查不到就退回最低一级（等级被后台放在不存在的级上时页面也别崩）。
     */
    private function levelOf(User $user): LevelConfig
    {
        $levels = $this->levelConfigRepository->allOrdered();
        $current = $levels->first(static fn (LevelConfig $level): bool => $level->level === (int) $user->level);

        if ($current instanceof LevelConfig) {
            return $current;
        }

        $lowest = $levels->first();
        if (! $lowest instanceof LevelConfig) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '等级配置缺失');
        }

        return $lowest;
    }

    private function nextLevelOf(LevelConfig $current): ?LevelConfig
    {
        $next = $this->levelConfigRepository->allOrdered()
            ->first(static fn (LevelConfig $level): bool => $level->level > $current->level);

        return $next instanceof LevelConfig ? $next : null;
    }

    /** 积分 → 分（「约合 ¥X.XX」）。汇率取 `point.per_yuan`，为 0 时返回 0。 */
    private function yuanFen(int $points): int
    {
        $pointPerYuan = (int) $this->configService->get('point.per_yuan', 0);

        return $pointPerYuan > 0 ? intdiv($points * 100, $pointPerYuan) : 0;
    }

    /**
     * 今天的起止（`Asia/Shanghai` 自然日）。
     *
     * @return array{0: string, 1: string}
     */
    private function today(): array
    {
        $start = Carbon::now()->startOfDay();

        return [$start->toDateTimeString(), $start->copy()->addDay()->toDateTimeString()];
    }

    /** 收益是女用户的功能（`docs/05` 第 4.8 节标题就是「收益与等级（女）」）。 */
    private function assertFemale(User $user): void
    {
        if (! $user->isFemale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '收益与等级只有女用户有');
        }
    }
}
