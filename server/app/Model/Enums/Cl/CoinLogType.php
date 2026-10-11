<?php

declare(strict_types=1);

namespace App\Model\Enums\Cl;

/**
 * 金币流水类型（docs/04 第 4 节 cl_coin_log.type）。
 */
enum CoinLogType: string
{
    case RECHARGE = 'RECHARGE';
    case MESSAGE = 'MESSAGE';
    case GIFT = 'GIFT';
    case VIP = 'VIP';
    case TASK = 'TASK';
    case ADMIN_DEDUCT = 'ADMIN_DEDUCT';

    /**
     * 计入「累计充值」的类型。
     */
    public function isRecharge(): bool
    {
        return $this === self::RECHARGE;
    }

    /**
     * 计入「累计消费」的类型。
     */
    public function isSpending(): bool
    {
        return \in_array($this, [self::MESSAGE, self::GIFT, self::VIP], true);
    }
}
