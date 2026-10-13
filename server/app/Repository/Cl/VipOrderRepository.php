<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\VipOrder;
use App\Repository\IRepository;

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
}
