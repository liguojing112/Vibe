<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Exception\ClBusinessException;
use App\Exception\Handler\ClBusinessExceptionHandler;
use App\Exception\Handler\ValidationExceptionHandler;
use App\Model\Enums\Cl\BizCode;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Server\Response;
use Hyperf\HttpMessage\Server\ResponsePlusProxy;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use Hyperf\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * App 接口的错误响应约定（docs/05 第 1、3 节）：
 * 业务错误 HTTP 200 + body 里的业务码；参数校验失败 body code = 422。
 *
 * @internal
 * @coversNothing
 */
final class ExceptionResponseTest extends TestCase
{
    use RunTestsInCoroutine;

    public function testBusinessExceptionReturnsHttp200WithBizCode(): void
    {
        $handler = ApplicationContext::getContainer()->get(ClBusinessExceptionHandler::class);
        $response = $handler->handle(
            new ClBusinessException(BizCode::COIN_NOT_ENOUGH, null, ['balance' => 4, 'needed' => 5]),
            new ResponsePlusProxy(new Response())
        );

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame(20001, $body['code']);
        self::assertSame('金币余额不足', $body['message']);
        self::assertSame(4, $body['data']['balance']);
        self::assertSame(5, $body['data']['needed']);
    }

    public function testBusinessExceptionCustomMessage(): void
    {
        $handler = ApplicationContext::getContainer()->get(ClBusinessExceptionHandler::class);
        $response = $handler->handle(
            new ClBusinessException(BizCode::ACCOUNT_BANNED, '因发布违规内容被封禁，解封时间 2026-11-01', ['reason' => '违规', 'until' => '2026-11-01 00:00:00']),
            new ResponsePlusProxy(new Response())
        );

        $body = json_decode((string) $response->getBody(), true);
        self::assertSame(10004, $body['code']);
        self::assertSame('因发布违规内容被封禁，解封时间 2026-11-01', $body['message']);
        self::assertSame('违规', $body['data']['reason']);
    }

    public function testValidationFailureReturns422InBody(): void
    {
        $handler = ApplicationContext::getContainer()->get(ValidationExceptionHandler::class);
        $response = $handler->handle(
            ValidationException::withMessages(['phone' => ['手机号不能为空']]),
            new ResponsePlusProxy(new Response())
        );

        $body = json_decode((string) $response->getBody(), true);
        self::assertSame(422, $body['code']);
        self::assertSame('手机号不能为空', $body['message']);
    }
}
