<?php

declare(strict_types=1);

namespace App\Model\Enums\Cl;

/**
 * 平台收入来源（docs/04 第 4 节 cl_platform_income.source）。
 */
enum PlatformIncomeSource: string
{
    case MESSAGE = 'MESSAGE';
    case GIFT = 'GIFT';
    case VIP = 'VIP';
}
