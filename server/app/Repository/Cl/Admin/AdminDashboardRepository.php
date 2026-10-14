<?php

declare(strict_types=1);

namespace App\Repository\Cl\Admin;

use App\Model\Cl\GiftRecord;
use App\Model\Cl\Message;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\RealName;
use App\Model\Cl\RechargeOrder;
use App\Model\Cl\Report;
use App\Model\Cl\User;
use App\Model\Cl\WithdrawOrder;

/**
 * 数据看板的统计查询（docs/07 第 3.1 节）。
 *
 * 两点约定：
 * 1. **「今日/近 30 天」都按服务端本地日期**（和 `cl_platform_income.income_date` 一个口径），
 *    传进来的是 `Y-m-d`，「截止到某天」在 `datetime` 列上要补到当天的 `23:59:59`
 *    —— 不补的话当天的数据全被挡在外面（T7.6 的提现列表就是这么空的）；
 * 2. 逐日序列**只返回有数据的天**，补零由服务层做 —— SQL 里造日期序列在 MySQL 里很别扭，
 *    而且图上要的是连续 30 个点。
 */
final class AdminDashboardRepository
{
    public function __construct(
        private readonly User $userModel,
        private readonly Message $messageModel,
        private readonly GiftRecord $giftRecordModel,
        private readonly RechargeOrder $rechargeOrderModel,
        private readonly ProfileAudit $profileAuditModel,
        private readonly RealName $realNameModel,
        private readonly Report $reportModel,
        private readonly WithdrawOrder $withdrawOrderModel,
    ) {}

    // ---------- 逐日序列（图用）----------

    /**
     * 每天新增的男/女用户数（按注册时间）。
     *
     * @return array<int,array{date: string, male: int, female: int}>
     */
    public function newUsersDaily(string $from, string $to): array
    {
        $rows = $this->userModel->newQuery()
            ->whereBetween('created_at', $this->range($from, $to))
            ->selectRaw('DATE(created_at) as day, gender, COUNT(*) as num')
            ->groupBy(['day', 'gender'])
            ->get();

        $days = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->day, 0, 10);
            $days[$date] ??= ['date' => $date, 'male' => 0, 'female' => 0];
            if ((int) $row->gender === User::GENDER_MALE) {
                $days[$date]['male'] += (int) $row->num;
            } else {
                $days[$date]['female'] += (int) $row->num;
            }
        }

        return array_values($days);
    }

    /**
     * 每天实际扣了钱的私信条数。
     *
     * 判据是 `price_coins > 0` 而**不是** `free_reason` ——
     * 免费的原因有空值、有四种枚举，而「有没有花钱」这一件事只有金币数说得准。
     *
     * @return array<int,array{date: string, num: int}>
     */
    public function paidMessagesDaily(string $from, string $to): array
    {
        $rows = $this->messageModel->newQuery()
            ->whereBetween('created_at', $this->range($from, $to))
            ->where('price_coins', '>', 0)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as num')
            ->groupBy('day')
            ->get();

        return $this->toDaily($rows, 'num');
    }

    /**
     * 每天送出的礼物金币总额。
     *
     * @return array<int,array{date: string, coins: int}>
     */
    public function giftCoinsDaily(string $from, string $to): array
    {
        $rows = $this->giftRecordModel->newQuery()
            ->whereBetween('created_at', $this->range($from, $to))
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(price_coins), 0) as coins')
            ->groupBy('day')
            ->get();

        return $this->toDaily($rows, 'coins');
    }

    /**
     * 每天的后台充值：金币数 + 实收金额（分）。
     *
     * `pay_channel = GIFT`（赠送）的单子金币算数、金额是 0，所以要一起统计进来，
     * 不能只筛有金额的。
     *
     * @return array<int,array{date: string, coins: int, amount_fen: int}>
     */
    public function rechargeDaily(string $from, string $to): array
    {
        $rows = $this->rechargeOrderModel->newQuery()
            ->whereBetween('created_at', $this->range($from, $to))
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(coins), 0) as coins, COALESCE(SUM(amount_fen), 0) as amount_fen')
            ->groupBy('day')
            ->get();

        $days = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->day, 0, 10);
            $days[$date] = [
                'date' => $date,
                'coins' => (int) $row->coins,
                'amount_fen' => (int) $row->amount_fen,
            ];
        }

        return array_values($days);
    }

    // ---------- 单值统计 ----------

    /** 这段时间内登录/活跃过的用户数（`last_active_at` 落在区间里就算）。 */
    public function activeUsers(string $from, string $to): int
    {
        return $this->userModel->newQuery()
            ->whereBetween('last_active_at', $this->range($from, $to))
            ->count();
    }

    /** 待审资料条数（`cl_profile_audit` 里待审的改动，资料审核页看的就是它）。 */
    public function countPendingProfile(): int
    {
        return $this->profileAuditModel->newQuery()
            ->where('status', ProfileAudit::STATUS_PENDING)
            ->count();
    }

    /** 待审实名条数。 */
    public function countPendingRealNames(): int
    {
        return $this->realNameModel->newQuery()
            ->where('status', RealName::STATUS_AUDITING)
            ->count();
    }

    /** 待处理举报条数。 */
    public function countPendingReports(): int
    {
        return $this->reportModel->newQuery()
            ->where('status', Report::STATUS_PENDING)
            ->count();
    }

    /** 待审消息条数（命中敏感词进队列的那些）。 */
    public function countPendingMessages(): int
    {
        return $this->messageModel->newQuery()
            ->where('review_status', Message::REVIEW_PENDING)
            ->count();
    }

    /**
     * 待审核提现：笔数 + 申请金额（分）。
     *
     * 金额取 `amount_fen`（用户申请的金额，docs/07 第 3.13 节那个数），
     * 不是 `actual_fen`（扣完手续费到账的）—— 财务要看的是「要准备多少钱」。
     *
     * @return array{count: int, amount_fen: int}
     */
    public function pendingWithdraw(): array
    {
        $row = $this->withdrawOrderModel->newQuery()
            ->where('status', WithdrawOrder::STATUS_PENDING)
            ->selectRaw('COUNT(*) as num, COALESCE(SUM(amount_fen), 0) as amount_fen')
            ->first();

        return [
            'count' => (int) ($row->num ?? 0),
            'amount_fen' => (int) ($row->amount_fen ?? 0),
        ];
    }

    // ---------- 内部 ----------

    /** `datetime` 列的闭区间：起止都补时分秒，不然当天整天的数据都漏掉。 */
    private function range(string $from, string $to): array
    {
        return [
            strlen($from) === 10 ? $from . ' 00:00:00' : $from,
            strlen($to) === 10 ? $to . ' 23:59:59' : $to,
        ];
    }

    /**
     * 逐日结果统一成 `['date' => ..., $column => ...]`。
     *
     * @return array<int,array<string,mixed>>
     */
    private function toDaily(iterable $rows, string $column): array
    {
        $days = [];
        foreach ($rows as $row) {
            $date = substr((string) $row->day, 0, 10);
            $days[$date] = ['date' => $date, $column => (int) $row->{$column}];
        }

        return array_values($days);
    }
}
