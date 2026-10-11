<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\ClBusinessException;
use Hyperf\Codec\Json;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Swow\Psr7\Message\ResponsePlusInterface;
use Throwable;

/**
 * App 接口业务异常处理器（docs/05 第 1、3 节）：
 * 业务错误 HTTP 状态码仍是 200，body 里的 code 用业务码。
 */
final class ClBusinessExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponsePlusInterface $response): ResponsePlusInterface
    {
        $this->stopPropagation();

        /** @var ClBusinessException $throwable */
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(200)
            ->withBody(new SwooleStream(Json::encode($throwable->getResponse()->toArray())));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ClBusinessException;
    }
}
