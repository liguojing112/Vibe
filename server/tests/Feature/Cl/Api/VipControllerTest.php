<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Cl\VipOrder;
use App\Model\Cl\VipPlan;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Model\Enums\Cl\CoinLogType;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 会员接口（docs/05 第 4.9 节）：`/vip/info`、`/vip/buy`。
 *
 * 会员规则本身在 `VipBillingTest` 里测，这里只管「接口接对了没有、鉴权挡住没有」。
 *
 * @internal
 * @coversNothing
 */
final class VipControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    /** @var array<int,int> 1 基的套餐 id */
    private array $planIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->planIds = [];
        $this->clearAll();
        $this->seedPlans();
        make(AppConfigService::class)->set('coin.per_yuan', 10);
        make(AppConfigService::class)->set('vip.daily_free_messages', 10);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testInfoReturnsPlansBenefitsAndBalance(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 500);

        $result = $this->get($male, '/api/v1/vip/info');

        self::assertSame(200, $result['code']);
        $data = $result['data'];
        self::assertFalse($data['is_vip']);
        self::assertNull($data['expire_at']);
        self::assertSame(500, $data['balance']);
        self::assertSame('会员不抵扣礼物费用；每日额外免费私信用完后按单价扣费', $data['notice']);

        self::assertCount(3, $data['plans']);
        self::assertSame('月卡', $data['plans'][0]['name']);
        self::assertSame(30, $data['plans'][0]['days']);
        self::assertSame(390, $data['plans'][0]['price_coins']);
        self::assertSame(39, $data['plans'][0]['price_yuan']);
        self::assertSame(299, $data['plans'][2]['price_yuan']);

        self::assertCount(4, $data['benefits']);
        self::assertSame('查看访客', $data['benefits'][0]['title']);
        self::assertSame('每日额外免费私信 10 条', $data['benefits'][3]['title']);
    }

    public function testInfoHidesDisabledPlans(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);
        VipPlan::query()->whereKey($this->planIds[1])->update(['enabled' => 0]);

        $result = $this->get($male, '/api/v1/vip/info');

        self::assertCount(2, $result['data']['plans']);
        self::assertNotContains('季卡', array_column($result['data']['plans'], 'name'));
    }

    public function testBuyMonthlyPlan(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 500);

        $result = $this->buy($male, $this->planIds[0], 'vip-order-1');

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertSame(110, $result['data']['balance']);
        self::assertSame(390, $result['data']['price_coins']);
        self::assertSame(30, $result['data']['days']);
        self::assertNotEmpty($result['data']['expire_at']);

        // 买完之后 /vip/info 就是会员了
        $info = $this->get($male, '/api/v1/vip/info');
        self::assertTrue($info['data']['is_vip']);
        self::assertSame($result['data']['expire_at'], $info['data']['expire_at']);
        self::assertSame(110, $info['data']['balance']);
    }

    public function testBuyWithInsufficientCoinsReturns20001(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);

        $result = $this->buy($male, $this->planIds[0], 'vip-order-2');

        self::assertSame(20001, $result['code']);
        self::assertSame('金币余额不足', $result['message']);
        self::assertSame(['balance' => 100, 'needed' => 390], $result['data']);
        self::assertSame(0, VipOrder::query()->where('user_id', $male->id)->count());
    }

    public function testBuyValidatesPlanId(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 500);

        $missingPlan = $this->client->post('/api/v1/vip/buy', [
            'client_order_id' => 'vip-order-3',
        ], $this->headers($male));
        self::assertSame(422, $missingPlan['code']);

        $missingOrder = $this->client->post('/api/v1/vip/buy', [
            'plan_id' => $this->planIds[0],
        ], $this->headers($male));
        self::assertSame(422, $missingOrder['code']);
    }

    public function testFemaleCannotOpenVipCenter(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $info = $this->get($female, '/api/v1/vip/info');
        self::assertSame(40001, $info['code']);

        $buy = $this->buy($female, $this->planIds[0], 'vip-order-4');
        self::assertSame(40001, $buy['code']);
    }

    public function testNeedLogin(): void
    {
        // form 校验跑在鉴权之前，所以「未登录」要带合法参数才走得到 401 那一步
        $info = $this->client->get('/api/v1/vip/info');
        self::assertSame(401, $info['code']);

        $buy = $this->client->post('/api/v1/vip/buy', [
            'plan_id' => 1,
            'client_order_id' => 'vip-order-5',
        ]);
        self::assertSame(401, $buy['code']);
    }

    /**
     * @return array<string,mixed>
     */
    private function buy(User $user, int $planId, string $orderNo): array
    {
        return $this->client->post('/api/v1/vip/buy', [
            'plan_id' => $planId,
            'client_order_id' => $orderNo,
        ], $this->headers($user));
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], $this->headers($user));
    }

    /**
     * @return array<string,string>
     */
    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokens[$user->id]];
    }

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
            make(CoinWalletService::class)->increase($user->id, $coins, CoinLogType::RECHARGE, 'test', $user->id, '测试充值');
        }

        return $user;
    }

    private function seedPlans(): void
    {
        $rows = [
            ['name' => '月卡', 'days' => 30, 'price_coins' => 390, 'sort' => 1],
            ['name' => '季卡', 'days' => 90, 'price_coins' => 990, 'sort' => 2],
            ['name' => '年卡', 'days' => 365, 'price_coins' => 2990, 'sort' => 3],
        ];

        foreach ($rows as $row) {
            $this->planIds[] = (int) VipPlan::query()->create(array_merge($row, ['enabled' => 1]))->id;
        }
    }

    private function clearAll(): void
    {
        VipOrder::query()->delete();
        VipPlan::query()->delete();
        CoinLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
