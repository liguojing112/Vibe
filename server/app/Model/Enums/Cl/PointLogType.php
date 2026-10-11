<?php

declare(strict_types=1);

namespace App\Model\Enums\Cl;

/**
 * 积分流水类型（docs/04 第 4 节 cl_point_log.type）。
 * 每种类型对应的 change / frozen_change 见 04 文档第 4 节的表。
 */
enum PointLogType: string
{
    case MESSAGE_INCOME = 'MESSAGE_INCOME';
    case GIFT_INCOME = 'GIFT_INCOME';
    case TASK = 'TASK';
    case WITHDRAW_FREEZE = 'WITHDRAW_FREEZE';
    case WITHDRAW_REFUND = 'WITHDRAW_REFUND';
    case WITHDRAW_PAID = 'WITHDRAW_PAID';
    case ADMIN_ADJUST = 'ADMIN_ADJUST';
}
