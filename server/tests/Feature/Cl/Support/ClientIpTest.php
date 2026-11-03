<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Support;

use App\Service\Cl\Support\ClientIp;
use Hyperf\HttpServer\Contract\RequestInterface;
use PHPUnit\Framework\TestCase;

/**
 * 真实客户端 IP 的取值优先级（登录限流和码支付 clientip 都用它）。
 *
 * @internal
 * @coversNothing
 */
final class ClientIpTest extends TestCase
{
    public function testRealIpWins(): void
    {
        $request = $this->request('1.2.3.4', '5.6.7.8, 9.9.9.9', '127.0.0.1');

        self::assertSame('1.2.3.4', ClientIp::of($request));
    }

    public function testForwardedFirstWhenNoRealIp(): void
    {
        $request = $this->request('', '5.6.7.8, 9.9.9.9', '127.0.0.1');

        self::assertSame('5.6.7.8', ClientIp::of($request));
    }

    public function testFallsBackToRemoteAddr(): void
    {
        $request = $this->request('', '', '10.0.0.5');

        self::assertSame('10.0.0.5', ClientIp::of($request));
    }

    private function request(string $realIp, string $forwarded, string $remoteAddr): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getHeaderLine')->willReturnCallback(
            static fn (string $name): string => match ($name) {
                'x-real-ip' => $realIp,
                'x-forwarded-for' => $forwarded,
                default => '',
            },
        );
        $request->method('getServerParams')->willReturn(['remote_addr' => $remoteAddr]);

        return $request;
    }
}
