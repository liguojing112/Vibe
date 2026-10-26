<?php

declare(strict_types=1);

namespace App\Service\Cl\Support;

/**
 * 业务单号生成（充值单、在线支付单）。
 *
 * 格式和原来后台充值单保持一致：前缀 + 年月日时分秒 + 4 位随机十六进制。
 * 在线支付单用 `P` 前缀，方便在日志和码支付后台里一眼区分。
 */
final class OrderNo
{
    public static function recharge(string $prefix = 'R'): string
    {
        return $prefix . date('YmdHis') . strtoupper(bin2hex(random_bytes(2)));
    }
}
