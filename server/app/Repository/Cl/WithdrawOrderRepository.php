<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\WithdrawOrder;
use App\Repository\IRepository;

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
