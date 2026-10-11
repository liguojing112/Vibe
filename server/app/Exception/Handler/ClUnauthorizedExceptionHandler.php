<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\ClUnauthorizedException;
use Hyperf\Codec\Json;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Swow\Psr7\Message\ResponsePlusInterface;
use Throwable;

/**
 * App 接口未登录 / token 失效 → HTTP 401 + {code: 401, message, data: []}（docs/05 第 1、2 节）。
 */
final class ClUnauthorizedExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponsePlusInterface $response): ResponsePlusInterface
    {
        $this->stopPropagation();

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(401)
            ->withBody(new SwooleStream(Json::encode([
                'code' => 401,
                'message' => $throwable->getMessage(),
                'data' => [],
            ])));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ClUnauthorizedException;
    }
}
