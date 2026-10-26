<?php

declare(strict_types=1);

namespace App\Repository\Cl\Admin;

use App\Model\Cl\CoinLog;
use App\Model\Cl\PlatformIncome;
use App\Model\Enums\Cl\PlatformIncomeSource;
use App\Model\Cl\PointLog;
use App\Model\Cl\RechargeOrder;
use Hyperf\Database\Model\Builder;

/**
 * 后台「资金管理」的查询（docs/07 第 3.11~3.14 节）。
 *
 * 流水和充值单都要带上**是谁**（用户昵称、操作人账号），所以两边都 join `cl_user` / `user`。
 * 注意这一层只**读**：钱怎么动全在 `AdminFinanceService` 里走钱包服务。
 */
final class AdminFinanceRepository
{
    public function __construct(
        private readonly RechargeOrder $rechargeModel,
        private readonly CoinLog $coinLogModel,
        private readonly PointLog $pointLogModel,
        private readonly PlatformIncome $incomeModel,
    ) {}

    /**
     * 充值记录（docs/07 第 3.11 节）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, coins: int, amount_fen: int}
     */
    public function rechargePage(array $filters, int $page, int $pageSize): array
    {
        // 合计只算**已支付**：待支付/已关闭的单没有真收到钱，算进去会让客服对账对不上
        $totals = $this->rechargeQuery($filters, false)
            ->where('cl_recharge_order.status', RechargeOrder::STATUS_PAID)
            ->selectRaw('COALESCE(SUM(cl_recharge_order.coins), 0) as coins, COALESCE(SUM(cl_recharge_order.amount_fen), 0) as amount_fen')
            ->first();

        $total = $this->rechargeQuery($filters)->count();
        $rows = $this->rechargeQuery($filters)
            ->orderByDesc('cl_recharge_order.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'order_no' => (string) $row->order_no,
                'user_id' => (int) $row->user_id,
                'user_nickname' => (string) $row->user_nickname,
                'user_avatar' => (string) ($row->user_avatar ?? ''),
                'coins' => (int) $row->coins,
                'amount_fen' => (int) $row->amount_fen,
                'pay_channel' => (string) $row->pay_channel,
                'source' => (string) ($row->source ?? RechargeOrder::SOURCE_ADMIN),
                'status' => (int) ($row->status ?? RechargeOrder::STATUS_PAID),
                'trade_no' => (string) ($row->trade_no ?? ''),
                'paid_at' => $row->paid_at?->toDateTimeString(),
                'remark' => (string) ($row->remark ?? ''),
                'operator_id' => $row->operator_id === null ? null : (int) $row->operator_id,
                'operator_name' => (string) ($row->operator_name ?? ''),
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return [
            'list' => $list,
            'total' => $total,
            // 底部合计（docs/07 第 3.11 节：合计金币与实收）
            'coins' => (int) ($totals->coins ?? 0),
            'amount_fen' => (int) ($totals->amount_fen ?? 0),
        ];
    }

    /**
     * 金币流水 / 积分流水（docs/07 第 3.12 节）。两张表结构差不多，用一个方法带过。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function walletLogPage(bool $points, array $filters, int $page, int $pageSize): array
    {
        $total = $this->walletLogQuery($points, $filters)->count();
        $rows = $this->walletLogQuery($points, $filters)
            ->orderByDesc($points ? 'cl_point_log.id' : 'cl_coin_log.id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $table = $points ? 'cl_point_log' : 'cl_coin_log';
        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'user_nickname' => (string) $row->user_nickname,
                'user_avatar' => (string) ($row->user_avatar ?? ''),
                'type' => (string) $row->type,
                'change' => (int) $row->change,
                'balance_after' => (int) $row->balance_after,
                'frozen_change' => $points ? (int) $row->frozen_change : null,
                'frozen_after' => $points ? (int) $row->frozen_after : null,
                'title' => (string) $row->title,
                'remark' => (string) ($row->remark ?? ''),
                'ref_type' => (string) ($row->ref_type ?? ''),
                'ref_id' => $row->ref_id === null ? null : (int) $row->ref_id,
                'operator_id' => $row->operator_id === null ? null : (int) $row->operator_id,
                'created_at' => $row->created_at?->toDateTimeString(),
                '_source' => $table,
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /**
     * 平台收入按日汇总（docs/07 第 3.14 节）。
     *
     * @return array<int,array<string,mixed>>
     */
    public function incomeDaily(string $from, string $to): array
    {
        $rows = $this->incomeModel->newQuery()
            ->whereBetween('income_date', [$from, $to])
            ->selectRaw('income_date, source, COALESCE(SUM(coins), 0) as coins, COALESCE(SUM(receiver_points), 0) as points')
            ->groupBy(['income_date', 'source'])
            ->orderByDesc('income_date')
            ->get();

        // 按天并成一行（表里是「一天 × 一种来源」一行）
        $days = [];
        foreach ($rows as $row) {
            // 底层可能是 date 也可能是 datetime，统一截成 Y-m-d
            $date = substr((string) $row->income_date, 0, 10);
            $days[$date] ??= [
                'date' => $date,
                'message_coins' => 0,
                'gift_coins' => 0,
                'vip_coins' => 0,
                'coins' => 0,
                'points' => 0,
            ];

            $coins = (int) $row->coins;
            $days[$date]['coins'] += $coins;
            $days[$date]['points'] += (int) $row->points;
            match ((string) $row->source) {
                PlatformIncomeSource::MESSAGE->value => $days[$date]['message_coins'] += $coins,
                PlatformIncomeSource::GIFT->value => $days[$date]['gift_coins'] += $coins,
                PlatformIncomeSource::VIP->value => $days[$date]['vip_coins'] += $coins,
                default => null,
            };
        }

        return array_values($days);
    }

    // ---------- 查询构造 ----------

    private function rechargeQuery(array $filters, bool $withSelect = true): Builder
    {
        /** @var Builder $query */
        $query = $this->rechargeModel->newQuery()
            ->join('cl_user', 'cl_user.id', '=', 'cl_recharge_order.user_id')
            ->leftJoin('user as op', 'op.id', '=', 'cl_recharge_order.operator_id');

        if ($withSelect) {
            $query->select([
                'cl_recharge_order.id', 'cl_recharge_order.order_no', 'cl_recharge_order.user_id',
                'cl_recharge_order.coins', 'cl_recharge_order.amount_fen', 'cl_recharge_order.pay_channel',
                'cl_recharge_order.source', 'cl_recharge_order.status', 'cl_recharge_order.trade_no',
                'cl_recharge_order.paid_at', 'cl_recharge_order.remark', 'cl_recharge_order.operator_id',
                'cl_recharge_order.created_at',
                'cl_user.nickname as user_nickname', 'cl_user.avatar as user_avatar',
                'op.username as operator_name',
            ]);
        }

        $this->exact($query, 'cl_recharge_order.user_id', $filters, 'user_id');
        $this->exact($query, 'cl_recharge_order.operator_id', $filters, 'operator_id');
        $this->exact($query, 'cl_recharge_order.pay_channel', $filters, 'pay_channel');
        $this->exact($query, 'cl_recharge_order.status', $filters, 'status');
        $this->range($query, 'cl_recharge_order.created_at', $filters);

        return $query;
    }

    private function walletLogQuery(bool $points, array $filters): Builder
    {
        $table = $points ? 'cl_point_log' : 'cl_coin_log';
        /** @var Builder $query */
        $query = $this->logModel($points)->newQuery()
            ->join('cl_user', 'cl_user.id', '=', $table . '.user_id')
            ->select([
                $table . '.id', $table . '.user_id', $table . '.type', $table . '.change',
                $table . '.balance_after', $table . '.title', $table . '.remark',
                $table . '.ref_type', $table . '.ref_id', $table . '.operator_id', $table . '.created_at',
                'cl_user.nickname as user_nickname', 'cl_user.avatar as user_avatar',
            ]);

        if ($points) {
            $query->addSelect(['cl_point_log.frozen_change', 'cl_point_log.frozen_after']);
        }

        $this->exact($query, $table . '.user_id', $filters, 'user_id');
        $this->exact($query, $table . '.type', $filters, 'type');
        $this->range($query, $table . '.created_at', $filters);

        return $query;
    }

    private function logModel(bool $points): CoinLog|PointLog
    {
        return $points ? $this->pointLogModel : $this->coinLogModel;
    }

    private function exact(Builder $query, string $column, array $filters, string $key): void
    {
        $value = $filters[$key] ?? null;
        if ($value === null || $value === '') {
            return;
        }
        $query->where($column, $value);
    }

    private function range(Builder $query, string $column, array $filters): void
    {
        if (! empty($filters['created_from'])) {
            $query->where($column, '>=', $this->dayStart((string) $filters['created_from']));
        }
        if (! empty($filters['created_to'])) {
            $query->where($column, '<=', $this->dayEnd((string) $filters['created_to']));
        }
    }

    private function dayStart(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 00:00:00' : $value;
    }

    /** 「截止到某天」要包含那一天。 */
    private function dayEnd(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value . ' 23:59:59' : $value;
    }
}
