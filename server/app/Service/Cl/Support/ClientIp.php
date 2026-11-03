<?php

declare(strict_types=1);

namespace App\Service\Cl\Support;

use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * 真实客户端 IP。
 *
 * 生产在 Nginx 后面，`remote_addr` 永远是 `127.0.0.1`。直接拿它做登录限流的 key，
 * 会把所有用户算成同一个 IP —— 2026-11-03 全站登录 10005 就是这个原因。
 *
 * 优先 `X-Real-IP`（Nginx 用 `$remote_addr` 覆盖，客户端伪造不了），
 * 其次 `X-Forwarded-For` 的第一个，最后才回退 `remote_addr`。
 */
final class ClientIp
{
    public static function of(RequestInterface $request): string
    {
        $realIp = trim($request->getHeaderLine('x-real-ip'));
        if ($realIp !== '') {
            return $realIp;
        }

        $forwarded = $request->getHeaderLine('x-forwarded-for');
        $comma = strpos($forwarded, ',');
        $first = trim($comma === false ? $forwarded : substr($forwarded, 0, $comma));
        if ($first !== '') {
            return $first;
        }

        return (string) ($request->getServerParams()['remote_addr'] ?? '0.0.0.0');
    }
}
