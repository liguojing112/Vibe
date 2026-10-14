<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\VipOrder;
use App\Repository\IRepository;
use Hyperf\Database\Model\Builder;

/**
 * @extends IRepository<VipOrder>
 */
final class VipOrderRepository extends IRepository
{
    public function __construct(
        protected readonly VipOrder $model
    ) {}

    /** 幂等：同一个订单号只开一次会员。 */
    public function findByOrderNo(string $orderNo): ?VipOrder
    {
        /** @var null|VipOrder $order */
        $order = $this->model->newQuery()->where('order_no', $orderNo)->first();

        return $order;
    }

    /**
     * 后台会员开通记录（docs/07 第 3.18 节）：单号、用户、套餐、天数、金币、来源、前后到期、操作人、时间。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pageForAdmin(array $filters, int $page, int $pageSize): array
    {
        $total = $this->adminQuery($filters)->count();
        $rows = $this->adminQuery($filters)
            ->orderByDesc('cl_vip_order.id')
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
                'plan_name' => (string) ($row->plan_name ?? '后台直接开通'),
                'days' => (int) $row->days,
                'price_coins' => (int) $row->price_coins,
                'source' => (int) $row->source,
                'expire_before' => $row->expire_before?->toDateTimeString(),
                'expire_after' => $row->expire_after?->toDateTimeString(),
                'operator_name' => (string) ($row->operator_name ?? ''),
                'remark' => (string) ($row->remark ?? ''),
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    private function adminQuery(array $filters): Builder
    {
        // `@var`：phpstan 认为 join 之后是 Query\Builder，运行期一直是模型的 Builder
        /** @var Builder $query */
        $query = $this->model->newQuery()
            ->join('cl_user', 'cl_user.id', '=', 'cl_vip_order.user_id')
            ->leftJoin('cl_vip_plan', 'cl_vip_plan.id', '=', 'cl_vip_order.plan_id')
            ->leftJoin('user as op', 'op.id', '=', 'cl_vip_order.operator_id')
            ->select([
                'cl_vip_order.id', 'cl_vip_order.order_no', 'cl_vip_order.user_id', 'cl_vip_order.days',
                'cl_vip_order.price_coins', 'cl_vip_order.source', 'cl_vip_order.expire_before',
                'cl_vip_order.expire_after', 'cl_vip_order.remark', 'cl_vip_order.created_at',
                'cl_user.nickname as user_nickname', 'cl_user.avatar as user_avatar',
                'cl_vip_plan.name as plan_name',
                'op.username as operator_name',
            ]);

        $userId = $filters['user_id'] ?? null;
        if ($userId !== null && $userId !== '') {
            $query->where('cl_vip_order.user_id', (int) $userId);
        }
        $source = $filters['source'] ?? null;
        if ($source !== null && $source !== '') {
            $query->where('cl_vip_order.source', (int) $source);
        }

        return $query;
    }

}
