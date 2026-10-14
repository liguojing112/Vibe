<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\WithdrawOrder;
use App\Repository\IRepository;
use Hyperf\Database\Model\Builder;

/**
 * @extends IRepository<WithdrawOrder>
 */
final class WithdrawOrderRepository extends IRepository
{
    public function __construct(
        protected readonly WithdrawOrder $model
    ) {}

    public function findByOrderNo(string $orderNo): ?WithdrawOrder
    {
        /** @var null|WithdrawOrder $order */
        $order = $this->model->newQuery()->where('order_no', $orderNo)->first();

        return $order;
    }

    /**
     * 事务里锁住这一行再读（并发审核同一张单时，第二个协程必须等第一个提交）。
     */
    public function lockById(int $id): ?WithdrawOrder
    {
        /** @var null|WithdrawOrder $order */
        $order = $this->model->newQuery()->whereKey($id)->lockForUpdate()->first();

        return $order;
    }

    /**
     * 某个用户今天的申请次数（**驳回的也算**，docs/06 第 9.1 节第 3 步）。
     */
    public function countToday(int $userId, string $from, string $to): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }

    /**
     * 后台提现审核列表（docs/07 第 3.13 节）。
     *
     * 带上女方的昵称、等级和实名姓名 —— 财务打款要拿实名去对收款人（docs/06 第 9.1 节第 8 步）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, amount_fen: int, actual_fen: int}
     */
    public function pageForAdmin(?int $status, string $from, string $to, int $page, int $pageSize): array
    {
        // 合计单独建一个查询：在已经 select 过的 builder 上再 selectRaw 会缺 group by
        $totals = $this->adminQuery($status, $from, $to, false)
            ->selectRaw('COALESCE(SUM(cl_withdraw_order.amount_fen), 0) as amount_fen, COALESCE(SUM(cl_withdraw_order.actual_fen), 0) as actual_fen')
            ->first();

        $total = $this->adminQuery($status, $from, $to)->count();
        $rows = $this->adminQuery($status, $from, $to)
            ->orderByDesc('cl_withdraw_order.id')
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
                'level' => (int) $row->level,
                'real_name' => (string) ($row->real_name ?? ''),
                'amount_fen' => (int) $row->amount_fen,
                'fee_fen' => (int) $row->fee_fen,
                'actual_fen' => (int) $row->actual_fen,
                'points' => (int) $row->points,
                'account_type' => (string) $row->account_type,
                'account_no' => (string) $row->account_no,
                'account_name' => (string) $row->account_name,
                'bank_name' => (string) ($row->bank_name ?? ''),
                'status' => (int) $row->status,
                'reject_reason' => (string) ($row->reject_reason ?? ''),
                'pay_proof' => (string) ($row->pay_proof ?? ''),
                'created_at' => $row->created_at?->toDateTimeString(),
                'paid_at' => $row->paid_at?->toDateTimeString(),
            ];
        }

        return [
            'list' => $list,
            'total' => $total,
            // 当前筛选的金额合计（docs/07 第 3.13 节）
            'amount_fen' => (int) ($totals->amount_fen ?? 0),
            'actual_fen' => (int) ($totals->actual_fen ?? 0),
        ];
    }

    private function adminQuery(?int $status, string $from, string $to, bool $withSelect = true): Builder
    {
        // `@var`：phpstan 认为 join 之后是 Query\Builder，运行期一直是模型的 Builder
        /** @var Builder $query */
        $query = $this->model->newQuery()
            ->join('cl_user', 'cl_user.id', '=', 'cl_withdraw_order.user_id')
            ->leftJoin('cl_real_name', 'cl_real_name.user_id', '=', 'cl_withdraw_order.user_id');

        if ($withSelect) {
            $query->select([
                'cl_withdraw_order.id', 'cl_withdraw_order.order_no', 'cl_withdraw_order.user_id',
                'cl_withdraw_order.amount_fen', 'cl_withdraw_order.fee_fen', 'cl_withdraw_order.actual_fen',
                'cl_withdraw_order.points', 'cl_withdraw_order.account_type', 'cl_withdraw_order.account_no',
                'cl_withdraw_order.account_name', 'cl_withdraw_order.bank_name', 'cl_withdraw_order.status',
                'cl_withdraw_order.reject_reason', 'cl_withdraw_order.pay_proof',
                'cl_withdraw_order.created_at', 'cl_withdraw_order.paid_at',
                'cl_user.nickname as user_nickname', 'cl_user.avatar as user_avatar', 'cl_user.level as level',
                // 收款人要跟实名对得上，所以把实名姓名带出来给财务看
                'cl_real_name.real_name as real_name',
            ]);
        }

        if ($status !== null) {
            $query->where('cl_withdraw_order.status', $status);
        }
        if ($from !== '') {
            $query->where('cl_withdraw_order.created_at', '>=', $from);
        }
        if ($to !== '') {
            $query->where('cl_withdraw_order.created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * 提现记录（P28），按时间倒序。
     *
     * @return array<int,array<string,mixed>>
     */
    public function pageForUser(int $userId, int $offset, int $limit): array
    {
        $rows = $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'order_no' => (string) $row->order_no,
                'amount_fen' => (int) $row->amount_fen,
                'points' => (int) $row->points,
                'fee_fen' => (int) $row->fee_fen,
                'actual_fen' => (int) $row->actual_fen,
                'account_type' => (string) $row->account_type,
                'account_no' => (string) $row->account_no,
                'account_name' => (string) $row->account_name,
                'status' => (int) $row->status,
                'status_text' => WithdrawOrder::STATUS_TEXTS[(int) $row->status] ?? '',
                'reject_reason' => $row->reject_reason,
                'created_at' => $row->created_at?->toDateTimeString(),
                'paid_at' => $row->paid_at?->toDateTimeString(),
            ];
        }

        return $list;
    }

    public function countForUser(int $userId): int
    {
        return $this->model->newQuery()->where('user_id', $userId)->count();
    }
}
