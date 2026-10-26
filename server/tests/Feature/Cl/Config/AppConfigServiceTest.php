<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Config;

use App\Model\Cl\Config;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class AppConfigServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    /**
     * docs/06 第 2 节的 key 清单（含 T0.6 新增的 chat.message_retention_months，以及协议文本）。
     * 这份清单必须与 cl_config_seeder_20261011.php 完全一致。
     */
    private const EXPECTED_KEYS = [
        'coin.per_yuan',
        'point.per_yuan',
        'chat.message_max_length',
        'chat.image_max_mb',
        'chat.message_retention_months',
        'chat.free_count_per_pair',
        'chat.mutual_follow_free',
        'chat.image_same_price',
        'share.unverified_factor',
        'share.unverified_apply_gift',
        'share.gift_rate',
        'vip.daily_free_messages',
        'vip.female_free_view',
        'withdraw.tiers',
        'withdraw.daily_limit',
        'withdraw.fee_rate',
        'withdraw.need_real_name',
        'withdraw.notice',
        'recharge.packs',
        'service.wechat',
        'service.qrcode_url',
        'service.phone',
        'pay.enabled',
        'pay.epay_url',
        'pay.epay_pid',
        'pay.epay_key',
        'pay.epay_type',
        'pay.notify_url',
        'pay.return_url',
        'pay.order_expire_minutes',
        'ai.base_url',
        'ai.model',
        'ai.api_key',
        'compliance.disclosure',
        'agreement.user',
        'agreement.privacy',
    ];

    private AppConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = ApplicationContext::getContainer()->get(AppConfigService::class);
        $this->service->forget();
    }

    public function testSeedKeysMatchDoc(): void
    {
        $actual = Config::query()->pluck('key')->all();
        $expected = self::EXPECTED_KEYS;
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
    }

    public function testGetInt(): void
    {
        self::assertSame(10, $this->service->get('coin.per_yuan'));
        self::assertSame(500, $this->service->get('chat.message_max_length'));
        self::assertSame(6, $this->service->get('chat.message_retention_months'));
    }

    public function testGetBool(): void
    {
        // T9.43/T9.44 移除了 feature.call_enabled 与 app.version，这里改用还在的 bool 配置
        self::assertTrue($this->service->get('chat.mutual_follow_free'));
        self::assertFalse($this->service->get('share.unverified_apply_gift'));
    }

    public function testGetJson(): void
    {
        self::assertSame([50, 100, 200, 500], $this->service->get('withdraw.tiers'));
        $packs = $this->service->get('recharge.packs');
        self::assertIsArray($packs);
        self::assertSame(60, $packs[0]['coins']);
    }

    /** 历史双重编码（`"[50,100]"`）也要能读成数组，迁移没跑时不至于让 App 拿到字符串。 */
    public function testGetJsonDecodesDoubleEncodedLegacyValue(): void
    {
        $row = Config::query()->where('key', 'withdraw.tiers')->firstOrFail();
        $original = (string) $row->value;
        $row->update(['value' => json_encode($original)]);
        $this->service->forget();

        self::assertSame([50, 100, 200, 500], $this->service->get('withdraw.tiers'));

        $row->update(['value' => $original]);
        $this->service->forget();
    }

    public function testGetString(): void
    {
        self::assertSame('提交后 1~3 个工作日内审核打款', $this->service->get('withdraw.notice'));
        self::assertNotSame('', $this->service->get('compliance.disclosure'));
    }

    public function testGetMissingReturnsDefault(): void
    {
        self::assertSame('fallback', $this->service->get('not.exists', 'fallback'));
        self::assertNull($this->service->get('not.exists'));
    }

    public function testGetGroup(): void
    {
        $withdraw = $this->service->getGroup('withdraw');
        self::assertArrayHasKey('withdraw.tiers', $withdraw);
        self::assertArrayHasKey('withdraw.notice', $withdraw);
        self::assertArrayNotHasKey('coin.per_yuan', $withdraw);
    }

    public function testSetClearsCache(): void
    {
        self::assertSame(3, $this->service->get('chat.free_count_per_pair'));

        self::assertTrue($this->service->set('chat.free_count_per_pair', 0));
        // 缓存已被清掉，再读拿到新值（而不是缓存里的 3）
        self::assertSame(0, $this->service->get('chat.free_count_per_pair'));

        self::assertTrue($this->service->set('chat.free_count_per_pair', 3));
        self::assertSame(3, $this->service->get('chat.free_count_per_pair'));
    }

    public function testSetBoolAndJson(): void
    {
        self::assertTrue($this->service->set('share.unverified_apply_gift', true));
        self::assertTrue($this->service->get('share.unverified_apply_gift'));
        self::assertTrue($this->service->set('share.unverified_apply_gift', false));
        self::assertFalse($this->service->get('share.unverified_apply_gift'));

        self::assertTrue($this->service->set('withdraw.tiers', [100, 200]));
        self::assertSame([100, 200], $this->service->get('withdraw.tiers'));
        // 还原
        self::assertTrue($this->service->set('withdraw.tiers', [50, 100, 200, 500]));
    }

    public function testSetUnknownKeyReturnsFalse(): void
    {
        self::assertFalse($this->service->set('not.exists', 1));
    }
}
