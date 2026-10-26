<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Pay;

use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Pay\EpayService;
use Hyperf\Guzzle\ClientFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * 码支付协议层（万世码支付 / 易支付 epay）：签名、验签、金额换算。
 *
 * 签名规则来自 https://xpay.wsmfk.com/doc/epay_md5：
 * 过滤空值和 sign/sign_type，参数名 ASCII 升序，拼 `a=b&c=d` 后直接接 KEY 做 md5。
 *
 * @internal
 * @coversNothing
 */
final class EpayServiceTest extends TestCase
{
    public function testSignSortsParamsAndSkipsSignFields(): void
    {
        $params = [
            'b' => '2',
            'a' => '1',
            'sign' => 'should-be-ignored',
            'sign_type' => 'MD5',
            'empty' => '',
        ];

        self::assertSame(md5('a=1&b=2' . 'SECRET'), EpayService::sign($params, 'SECRET'));
    }

    public function testSignKeepsZeroValue(): void
    {
        // '0' 是合法值，不能被 array_filter 当成空值丢掉
        self::assertSame(md5('a=0' . 'K'), EpayService::sign(['a' => '0'], 'K'));
    }

    public function testVerifyNotifyAcceptsValidSignature(): void
    {
        $service = $this->service([
            'pay.epay_pid' => '1001',
            'pay.epay_key' => 'SECRET',
        ]);

        $params = [
            'pid' => '1001',
            'trade_no' => 'T1',
            'out_trade_no' => 'P1',
            'type' => 'alipay',
            'name' => '金币',
            'money' => '6.00',
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'MD5',
        ];
        $params['sign'] = EpayService::sign($params, 'SECRET');

        self::assertTrue($service->verifyNotify($params));
    }

    public function testVerifyNotifyRejectsWrongSign(): void
    {
        $service = $this->service([
            'pay.epay_pid' => '1001',
            'pay.epay_key' => 'SECRET',
        ]);

        self::assertFalse($service->verifyNotify([
            'pid' => '1001',
            'out_trade_no' => 'P1',
            'money' => '6.00',
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'MD5',
            'sign' => 'bad-sign',
        ]));
    }

    public function testVerifyNotifyRejectsWrongPid(): void
    {
        $service = $this->service([
            'pay.epay_pid' => '1001',
            'pay.epay_key' => 'SECRET',
        ]);

        $params = [
            'pid' => '9999',
            'out_trade_no' => 'P1',
            'money' => '6.00',
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'MD5',
        ];
        $params['sign'] = EpayService::sign($params, 'SECRET');

        self::assertFalse($service->verifyNotify($params));
    }

    public function testEnabledNeedsSwitchAndCredentials(): void
    {
        $service = $this->service([
            'pay.enabled' => true,
            'pay.epay_pid' => '1001',
            'pay.epay_key' => 'SECRET',
        ]);
        self::assertTrue($service->enabled());

        $missingKey = $this->service([
            'pay.enabled' => true,
            'pay.epay_pid' => '1001',
            'pay.epay_key' => '',
        ]);
        self::assertFalse($missingKey->enabled());

        $switchedOff = $this->service([
            'pay.enabled' => false,
            'pay.epay_pid' => '1001',
            'pay.epay_key' => 'SECRET',
        ]);
        self::assertFalse($switchedOff->enabled());
    }

    public function testFenToYuanKeepsTwoDecimals(): void
    {
        self::assertSame('6.00', EpayService::fenToYuan(600));
        self::assertSame('0.05', EpayService::fenToYuan(5));
        self::assertSame('128.00', EpayService::fenToYuan(12800));
    }

    public function testYuanToFenParsesAndRejectsBadInput(): void
    {
        self::assertSame(600, EpayService::yuanToFen('6.00'));
        self::assertSame(650, EpayService::yuanToFen('6.5'));
        self::assertSame(5, EpayService::yuanToFen('0.05'));
        self::assertNull(EpayService::yuanToFen('abc'));
        self::assertNull(EpayService::yuanToFen('-1'));
        self::assertNull(EpayService::yuanToFen('1.234'));
    }

    /**
     * @param array<string,mixed> $values
     */
    private function service(array $values): EpayService
    {
        $config = $this->createMock(AppConfigService::class);
        $config->method('get')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => $values[$key] ?? $default,
        );

        return new EpayService(
            $this->createMock(ClientFactory::class),
            $config,
            $this->createMock(LoggerInterface::class),
        );
    }
}
