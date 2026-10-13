<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\GiftRecord;
use App\Repository\IRepository;

/**
 * @extends IRepository<GiftRecord>
 */
final class GiftRecordRepository extends IRepository
{
    public function __construct(
        protected readonly GiftRecord $model
    ) {}

    /** 幂等：同一个人同一个订单号只记一次。 */
    public function findByOrderId(int $senderId, string $clientOrderId): ?GiftRecord
    {
        /** @var null|GiftRecord $record */
        $record = $this->model->newQuery()
            ->where('sender_id', $senderId)
            ->where('client_order_id', $clientOrderId)
            ->first();

        return $record;
    }
}
