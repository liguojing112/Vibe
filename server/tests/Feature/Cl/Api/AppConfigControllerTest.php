<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Service\Cl\Config\AppConfigService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * App 启动配置与协议接口（docs/05 第 4.1 节）。两个接口都免鉴权。
 *
 * @internal
 * @coversNothing
 */
final class AppConfigControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        // 清掉配置缓存，保证读到的是种子后的最新值
        ApplicationContext::getContainer()->get(AppConfigService::class)->forget();
    }

    public function testConfigReturnsAllFieldsFromDoc(): void
    {
        $result = $this->client->get('/api/v1/app/config');

        self::assertSame(200, $result['code']);
        $data = $result['data'];

        foreach (['call_enabled', 'compliance_disclosure', 'service', 'chat_max_length', 'image_max_mb', 'cities', 'tags', 'version'] as $key) {
            self::assertArrayHasKey($key, $data, "启动配置缺少字段 {$key}");
        }

        self::assertFalse($data['call_enabled']);
        self::assertSame(500, $data['chat_max_length']);
        self::assertSame(5, $data['image_max_mb']);
        self::assertIsArray($data['service']);
        foreach (['wechat', 'qrcode_url', 'phone'] as $key) {
            self::assertArrayHasKey($key, $data['service']);
        }
        self::assertNotSame('', (string) $data['version']);
    }

    public function testConfigReturnsSeededCitiesAndTags(): void
    {
        $data = $this->client->get('/api/v1/app/config')['data'];

        self::assertCount(10, $data['cities']);
        self::assertSame('北京', $data['cities'][0]);

        self::assertCount(10, $data['tags']);
        self::assertSame('旅行', $data['tags'][0]['name']);
        self::assertIsInt($data['tags'][0]['id']);
    }

    public function testComplianceDisclosureIsNeverEmpty(): void
    {
        $data = $this->client->get('/api/v1/app/config')['data'];

        self::assertNotSame('', $data['compliance_disclosure']);
        self::assertStringContainsString('付费聊天', $data['compliance_disclosure']);
    }

    public function testAgreementUser(): void
    {
        $result = $this->client->get('/api/v1/app/agreement/user');

        self::assertSame(200, $result['code']);
        self::assertSame('user', $result['data']['type']);
        self::assertNotSame('', $result['data']['content']);
        self::assertStringContainsString('付费聊天', $result['data']['content']);
    }

    public function testAgreementPrivacy(): void
    {
        $result = $this->client->get('/api/v1/app/agreement/privacy');

        self::assertSame(200, $result['code']);
        self::assertSame('privacy', $result['data']['type']);
        self::assertNotSame('', $result['data']['content']);
    }

    public function testAgreementInvalidTypeReturns422(): void
    {
        self::assertSame(422, $this->client->get('/api/v1/app/agreement/unknown')['code']);
    }

    public function testBothEndpointsWorkWithoutToken(): void
    {
        self::assertSame(200, $this->client->get('/api/v1/app/config')['code']);
        self::assertSame(200, $this->client->get('/api/v1/app/agreement/user')['code']);
    }
}
