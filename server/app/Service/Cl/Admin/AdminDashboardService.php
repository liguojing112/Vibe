<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Repository\Cl\Admin\AdminDashboardRepository;
use App\Repository\Cl\Admin\AdminFinanceRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\PlatformIncomeService;

/**
 * 数据看板（docs/07 第 3.1 节）。
 *
 * 「今日」取的是服务端本地日期，和 `cl_platform_income.income_date` 同一个口径；
 * 近 30 天窗口的最后一天就是今天，所以**卡片和图的最后一天是同一批数据**，
 * 不会出现「卡片说今天 5 条、图的最后一个点说 4 条」。
 *
 * 逐日序列在 SQL 里只返回有数据的天，**补零在这里做** —— 图上要的是连续 30 个点，
 * 缺天的话折线会直接把两天连起来，看着像天天都有量。
 */
final class AdminDashboardService
{
    /** 图表窗口天数（docs/07 第 3.1 节：近 30 天）。 */
    public const CHART_DAYS = 30;

    public function __construct(
        private readonly AdminDashboardRepository $repository,
        private readonly AdminFinanceRepository $financeRepository,
        private readonly AppConfigService $configService,
    ) {}

    /**
     * @return array{date: string, days: int, cards: array<string,int>, chart: array<string,array<int,mixed>>}
     */
    public function overview(): array
    {
        $to = date('Y-m-d');
        $from = date('Y-m-d', strtotime('-' . (self::CHART_DAYS - 1) . ' days'));
        $dates = $this->dateRange($from, $to);

        $newUsers = $this->indexByDate($this->repository->newUsersDaily($from, $to));
        $paidMessages = $this->indexByDate($this->repository->paidMessagesDaily($from, $to));
        $giftCoins = $this->indexByDate($this->repository->giftCoinsDaily($from, $to));
        $recharge = $this->indexByDate($this->repository->rechargeDaily($from, $to));
        $income = $this->indexByDate($this->financeRepository->incomeDaily($from, $to));

        $coinPerYuan = (int) $this->configService->get('coin.per_yuan', 10);
        $pointPerYuan = (int) $this->configService->get('point.per_yuan', 100);

        // 「今天」= 窗口最后一天。这天可能一条数据都没有，所以下面全部按日期取值、取不到就是 0。
        $today = $to;
        $withdraw = $this->repository->pendingWithdraw();

        return [
            'date' => $today,
            'days' => self::CHART_DAYS,
            'cards' => [
                'new_male' => (int) ($newUsers[$today]['male'] ?? 0),
                'new_female' => (int) ($newUsers[$today]['female'] ?? 0),
                'active_users' => $this->repository->activeUsers($today, $today),
                'paid_messages' => (int) ($paidMessages[$today]['num'] ?? 0),
                'gift_coins' => (int) ($giftCoins[$today]['coins'] ?? 0),
                'recharge_coins' => (int) ($recharge[$today]['coins'] ?? 0),
                'recharge_amount_fen' => (int) ($recharge[$today]['amount_fen'] ?? 0),
                'platform_income_fen' => $this->incomeFen($income[$today] ?? null, $coinPerYuan, $pointPerYuan),
                'pending_profile' => $this->repository->countPendingProfile(),
                'pending_realname' => $this->repository->countPendingRealNames(),
                'pending_reports' => $this->repository->countPendingReports(),
                'pending_messages' => $this->repository->countPendingMessages(),
                'pending_withdraw_count' => $withdraw['count'],
                'pending_withdraw_fen' => $withdraw['amount_fen'],
            ],
            'chart' => [
                'dates' => $dates,
                'new_male' => $this->column($dates, $newUsers, 'male'),
                'new_female' => $this->column($dates, $newUsers, 'female'),
                'recharge_amount_fen' => $this->column($dates, $recharge, 'amount_fen'),
                // 毛收入要按天的汇率折算，不能先加总再折算 —— 汇率是运营随时可改的
                'platform_income_fen' => array_map(
                    fn (string $date): int => $this->incomeFen($income[$date] ?? null, $coinPerYuan, $pointPerYuan),
                    $dates,
                ),
            ],
        ];
    }

    /** 某天的毛收入（分）；那天没有收入明细就是 0。 */
    private function incomeFen(?array $row, int $coinPerYuan, int $pointPerYuan): int
    {
        if ($row === null) {
            return 0;
        }

        return PlatformIncomeService::incomeFen((int) $row['coins'], (int) $row['points'], $coinPerYuan, $pointPerYuan);
    }

    /** 连续日期列表（含首尾）。 */
    private function dateRange(string $from, string $to): array
    {
        $dates = [];
        $cursor = strtotime($from);
        $end = strtotime($to);
        while ($cursor <= $end) {
            $dates[] = date('Y-m-d', $cursor);
            $cursor = strtotime('+1 day', $cursor);
        }

        return $dates;
    }

    /**
     * 让 repository 返回的「只有有数据的那些天」变成按日期取值的 map。
     *
     * @return array<string,array<string,mixed>>
     */
    private function indexByDate(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['date']] = $row;
        }

        return $map;
    }

    /**
     * 把 map 拉平成和 `$dates` 一一对应的数组，缺的天补 0。
     *
     * @return array<int,int>
     */
    private function column(array $dates, array $map, string $key): array
    {
        return array_map(static fn (string $date): int => (int) ($map[$date][$key] ?? 0), $dates);
    }
}
