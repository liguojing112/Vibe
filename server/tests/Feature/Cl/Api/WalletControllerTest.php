<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Message;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Enums\Cl\CoinLogType;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 金币接口（T5.1 / docs/05 第 4.7 节）：`/wallet/coin`、`/wallet/coin/logs`、`/wallet/recharge-info`。
 *
 * 这几个接口**只给男用户**（P13/P22/P24 都是男号页面），女用户调用一律 40001。
 *
 * @internal
 * @coversNothing
 */
final class WalletControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->clearAll();
        $this->seedLevels();
        $this->resetConfig();
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        $this->clearAll();
        parent::tearDown();
    }

    public function testCoinReturnsBalanceAndPriceRange(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 360);

        $result = $this->get($male, '/api/v1/wallet/coin');

        self::assertSame(200, $result['code']);
        self::assertSame(360, $result['data']['balance']);
        // Lv1~Lv5 的私信单价是 5/8/10/15/20
        self::assertSame(5, $result['data']['message_price_min']);
        self::assertSame(20, $result['data']['message_price_max']);
        self::assertFalse($result['data']['call_enabled']);
        // 开关关着时不该出现语音/视频价格（docs/01 第 4 节）
        self::assertArrayNotHasKey('voice_price_min', $result['data']);
        self::assertArrayNotHasKey('video_price_min', $result['data']);
    }

    public function testCoinPriceRangeFollowsLevelConfig(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);
        // 后台把 Lv1 单价从 5 改成 8（docs/06 第 10 节场景「改配置后 App 按新规则显示」）
        make(LevelConfigRepository::class)->findByLevel(1)->update(['message_price' => 8]);

        $result = $this->get($male, '/api/v1/wallet/coin');

        self::assertSame(8, $result['data']['message_price_min']);
    }

    /**
     * T9.44 有意移除了 `feature.call_enabled`：语音/视频这一轮不做，
     * 服务端按 false 处理，钱包接口永远不返回语音/视频价格。
     */
    public function testCallPricesAreNeverReturnedAfterSwitchRemoved(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);

        $result = $this->get($male, '/api/v1/wallet/coin');

        self::assertFalse($result['data']['call_enabled']);
        self::assertArrayNotHasKey('voice_price_min', $result['data']);
        self::assertArrayNotHasKey('video_price_min', $result['data']);
    }

    public function testCoinRequiresLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/wallet/coin')['code']);
        self::assertSame(401, $this->client->get('/api/v1/wallet/coin/logs')['code']);
        self::assertSame(401, $this->client->get('/api/v1/wallet/recharge-info')['code']);
    }

    /** 金币是男用户的：女用户调用一律 40001（App 里也没有这些入口）。 */
    public function testCoinIsMaleOnly(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        self::assertSame(40001, $this->get($female, '/api/v1/wallet/coin')['code']);
        self::assertSame(40001, $this->get($female, '/api/v1/wallet/coin/logs')['code']);
        self::assertSame(40001, $this->get($female, '/api/v1/wallet/recharge-info')['code']);
    }

    // ---------- 收支明细 ----------

    public function testCoinLogsFilterByType(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 200);
        $this->spend($male, 5, CoinLogType::MESSAGE, '私信 · 小满');
        $this->spend($male, 120, CoinLogType::GIFT, '礼物 · 浪漫花束');

        $all = $this->get($male, '/api/v1/wallet/coin/logs?type=all');
        self::assertSame(3, $all['data']['total'], '充值 + 私信 + 礼物');
        self::assertSame(20, $all['data']['page_size']);

        // 最新的在最前面
        self::assertSame('礼物 · 浪漫花束', $all['data']['list'][0]['title']);
        self::assertSame(-120, $all['data']['list'][0]['change']);
        self::assertSame('GIFT', $all['data']['list'][0]['type']);
        self::assertSame(200 - 5 - 120, $all['data']['list'][0]['balance_after']);
        self::assertNotNull($all['data']['list'][0]['created_at']);

        $recharge = $this->get($male, '/api/v1/wallet/coin/logs?type=recharge');
        self::assertSame(1, $recharge['data']['total']);
        self::assertSame('RECHARGE', $recharge['data']['list'][0]['type']);
        self::assertSame(200, $recharge['data']['list'][0]['change'], '充值是正数');

        $spend = $this->get($male, '/api/v1/wallet/coin/logs?type=spend');
        self::assertSame(2, $spend['data']['total']);
        self::assertSame(['礼物 · 浪漫花束', '私信 · 小满'], array_column($spend['data']['list'], 'title'));
    }

    public function testCoinLogsPaginate(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 1000);
        for ($i = 1; $i <= 25; ++$i) {
            $this->spend($male, 1, CoinLogType::MESSAGE, '私信 ' . $i);
        }

        $page1 = $this->get($male, '/api/v1/wallet/coin/logs?type=spend&page=1');
        self::assertSame(25, $page1['data']['total']);
        self::assertCount(20, $page1['data']['list']);
        self::assertTrue($page1['data']['has_more']);

        $page2 = $this->get($male, '/api/v1/wallet/coin/logs?type=spend&page=2');
        self::assertCount(5, $page2['data']['list']);
        self::assertFalse($page2['data']['has_more']);
    }

    public function testCoinLogsRejectsUnknownType(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);

        self::assertSame(422, $this->get($male, '/api/v1/wallet/coin/logs?type=whatever')['code']);
    }

    // ---------- 充值信息 ----------

    public function testRechargeInfoCarriesPacksServiceAndCompliance(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 66);
        $config = make(AppConfigService::class);
        $config->set('service.wechat', 'changliao_kf');
        $config->set('service.phone', '400-000-0000');

        $result = $this->get($male, '/api/v1/wallet/recharge-info');

        self::assertSame(200, $result['code']);
        self::assertSame(66, $result['data']['balance']);
        // 后台配的 6 个档位，顺序不变
        self::assertSame(
            [
                ['coins' => 60, 'yuan' => 6],
                ['coins' => 300, 'yuan' => 30],
                ['coins' => 680, 'yuan' => 68],
                ['coins' => 1280, 'yuan' => 128],
                ['coins' => 2980, 'yuan' => 298],
                ['coins' => 6480, 'yuan' => 648],
            ],
            $result['data']['packs'],
        );
        self::assertSame('changliao_kf', $result['data']['service']['wechat']);
        self::assertSame('400-000-0000', $result['data']['service']['phone']);
        // 铁律 13：合规声明必须有内容
        self::assertNotSame('', $result['data']['compliance_disclosure']);
        self::assertSame('可直接在线支付，也可联系客服充值；客服确认后金币到账', $result['data']['notice']);
        // 测试库默认没开在线支付，App 只显示客服充值
        self::assertFalse($result['data']['pay_enabled']);
    }

    /** 合规声明被后台清空时服务端兜底（docs/06 第 2.6 节：不能为空）。 */
    public function testComplianceDisclosureNeverEmpty(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);
        make(AppConfigService::class)->set('compliance.disclosure', '   ');

        $result = $this->get($male, '/api/v1/wallet/recharge-info');

        self::assertNotSame('', trim((string) $result['data']['compliance_disclosure']));
    }

    /** 档位配置被写坏（不是数组）时返回空列表，而不是让 App 解析崩掉。 */
    public function testBrokenPacksFallBackToEmptyList(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);
        make(AppConfigService::class)->set('recharge.packs', 'not-json');

        $result = $this->get($male, '/api/v1/wallet/recharge-info');

        self::assertSame(200, $result['code']);
        self::assertSame([], $result['data']['packs']);
    }

    // ---------- 工具 ----------

    private function registerUser(int $gender, int $coins): User
    {
        $phone = '13500000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => '用户' . $this->sequence,
        ]);
        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $this->tokens[$user->id] = (string) $result['data']['access_token'];

        if ($coins > 0) {
            make(CoinWalletService::class)->increase(
                $user->id,
                $coins,
                CoinLogType::RECHARGE,
                'test',
                $user->id,
                '客服充值',
            );
        }

        return $user;
    }

    /** 造一条消费流水（走钱包服务，`check-money.sh` 只允许它改余额）。 */
    private function spend(User $user, int $amount, CoinLogType $type, string $title): void
    {
        make(CoinWalletService::class)->decrease(
            $user->id,
            $amount,
            $type,
            'test',
            ++$this->sequence,
            $title,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], ['Authorization' => 'Bearer ' . $this->tokens[$user->id]]);
    }

    /** 等级配置按 docs/06 第 2.3 节的默认值来。 */
    private function seedLevels(): void
    {
        $repository = make(LevelConfigRepository::class);
        $rows = [
            1 => [0, 5, 3000, 60, 120],
            2 => [20000, 8, 3500, 80, 160],
            3 => [100000, 10, 4000, 100, 200],
            4 => [300000, 15, 4500, 150, 300],
            5 => [1000000, 20, 5000, 200, 400],
        ];

        foreach ($rows as $level => [$threshold, $price, $rate, $voice, $video]) {
            $attributes = [
                'name' => 'Lv' . $level,
                'threshold_points' => $threshold,
                'message_price' => $price,
                'message_share_rate' => $rate,
                'voice_price' => $voice,
                'video_price' => $video,
            ];
            $existing = $repository->findByLevel($level);
            if ($existing instanceof LevelConfig) {
                $existing->update($attributes);
            } else {
                LevelConfig::query()->create(array_merge(['level' => $level], $attributes));
            }
        }
    }

    private function resetConfig(): void
    {
        $config = make(AppConfigService::class);
        $config->set('service.wechat', '');
        $config->set('service.qrcode_url', '');
        $config->set('service.phone', '');
        $config->set('compliance.disclosure', '本平台为付费聊天服务，部分女用户为平台签约陪聊人员。');
        // 在线支付默认关（测试它自己的用例会单独打开）
        $config->set('pay.enabled', 0);
        $config->set('pay.epay_pid', '');
        $config->set('pay.epay_key', '');
        // ⚠️ 这里必须传**数组**而不是 JSON 字符串：`AppConfigService::set()` 会按这一项的
        // value_type（json）再编码一次，传字符串就变成「双重编码」—— 本测试里看着没事
        // （服务端会兜一层解码），但会把后面跑的 `AppConfigServiceTest::testGetJson` 弄红（踩过）。
        $config->set('recharge.packs', [
            ['coins' => 60, 'yuan' => 6],
            ['coins' => 300, 'yuan' => 30],
            ['coins' => 680, 'yuan' => 68],
            ['coins' => 1280, 'yuan' => 128],
            ['coins' => 2980, 'yuan' => 298],
            ['coins' => 6480, 'yuan' => 648],
        ]);
    }

    private function clearAll(): void
    {
        CoinLog::query()->delete();
        Message::query()->delete();
        Conversation::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
