<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * App 接口未登录 / token 失效（docs/05 第 1、2 节）：HTTP 401 + {code: 401}。
 */
class ClUnauthorizedException extends \RuntimeException
{
    public function __construct(string $message = '请先登录')
    {
        parent::__construct($message);
    }
}
