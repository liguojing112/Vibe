<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Vip;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Cl\VipOrder;
use App\Model\Cl\VipPlan;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Vip\VipService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 会员（docs/06 第 6 节 + 第 10 节场景 21、22）。
 *
 * 场景 21 的两句话都要断言到：
 * 1. 金币购买月卡 → 扣 390 金币、到期时间 +30 天；
 * 2. **未过期续费是在原到期时间上加**（不是从现在加）。
 *
 * @internal
 * @coversNothing
 */
final class VipBillingTest extends TestCase
{
    use RunTestsInCoroutine;

    private VipService $vip;

    /** 种下去的 3 个套餐 id（1 基：1 = 月卡 30 天 390 金币）。 */
    private array $planIds = [];

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->resetConfig();
        $this->seedPlans();

        $this->vip = make(VipService::class);
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 场景 21：金币购买月卡 ----------

    public function testScene21BuyMonthlyPlanDeducts390AndExtends30Days(): void
    {
        $male = $this->makeMale(balance: 500);

        $result = $this->vip->buy($male, $this->planId(1), $this->orderNo());

        self::assertSame(110, $result['balance'], '500 - 390');
        self::assertSame(30, $result['days']);
        self::assertSame(390, $result['price_coins']);
        $this->assertExpireInDays(30, $result['expire_at'], '没开过会员就从现在开始加 30 天');

        // 订单记下来了（对账要看）
        $order = VipOrder::query()->where('user_id', $male->id)->firstOrFail();
        self::assertSame(VipOrder::SOURCE_COIN, (int) $order->source);
        self::assertSame(390, (int) $order->price_coins);
        self::assertSame(30, (int) $order->days);
        self::assertNull($order->expire_before, '第一次开通前没有到期时间');
        self::assertSame($result['expire_at'], $order->expire_after->toDateTimeString());

        // 金币流水（type = VIP，docs/06 第 6 节）
        $log = CoinLog::query()->where('user_id', $male->id)->where('type', CoinLogType::VIP->value)->firstOrFail();
        self::assertSame(-390, (int) $log->change);
        self::assertSame(110, (int) $log->balance_after);
        self::assertSame((int) $order->id, (int) $log->ref_id);
        self::assertSame(VipService::REF_TYPE, $log->ref_type);

        // 用户真的成了会员
        self::assertTrue($male->refresh()->isVip());
    }

    public function testScene21RenewBeforeExpiryAddsDaysOnTopOfCurrentExpire(): void
    {
        $oldExpire = Carbon::now()->addDays(10);
        $male = $this->makeMale(balance: 500, vipExpireAt: $oldExpire->toDateTimeString());

        $result = $this->vip->buy($male, $this->planId(1), $this->orderNo());

        self::assertSame(
            $oldExpire->getTimestamp() + 30 * 86400,
            Carbon::parse($result['expire_at'])->getTimestamp(),
            '未过期续费要在原到期时间上加 30 天，不是从现在加',
        );

        $order = VipOrder::query()->where('user_id', $male->id)->firstOrFail();
        self::assertSame($oldExpire->toDateTimeString(), $order->expire_before->toDateTimeString(), '续费前的到期时间也要记下来');
        self::assertSame(110, $result['balance']);
    }

    public function testExpiredMembershipStartsFromNowInsteadOfTheOldExpire(): void
    {
        $male = $this->makeMale(balance: 500, vipExpireAt: Carbon::now()->subDays(100)->toDateTimeString());

        $result = $this->vip->buy($male, $this->planId(1), $this->orderNo());

        $this->assertExpireInDays(30, $result['expire_at'], '已经过期了就从现在开始算，不能从过期那天往后接');
        self::assertNull(VipOrder::query()->where('user_id', $male->id)->firstOrFail()->expire_before, '过期了就没有「续费前」');
    }

    public function testSeasonsAndYearPlansUseTheirOwnDaysAndPrice(): void
    {
        $male = $this->makeMale(balance: 5000);

        $season = $this->vip->buy($male, $this->planId(2), $this->orderNo());
        self::assertSame(90, $season['days']);
        self::assertSame(4010, $season['balance'], '5000 - 990');

        $year = $this->vip->buy($male, $this->planId(3), $this->orderNo());
        self::assertSame(365, $year['days']);
        self::assertSame(1020, $year['balance'], '4010 - 2990');

        // 第二单从第一单的到期时间往后接（季卡 90 天 + 年卡 365 天）
        $this->assertExpireInDays(90 + 365, $year['expire_at']);
    }

    /**
     * 到期时间应该落在「现在 + N 天」附近。
     *
     * 不让测试自己再调一次 `Carbon::now()` 去精确比：两次调用跨过一秒就挂了（真跑出来过）。
     */
    private function assertExpireInDays(int $days, ?string $expireAt, string $message = ''): void
    {
        self::assertNotNull($expireAt, $message);
        $expected = Carbon::now()->addDays($days)->getTimestamp();
        $actual = Carbon::parse($expireAt)->getTimestamp();

        self::assertGreaterThanOrEqual($expected - 5, $actual, $message);
        self::assertLessThanOrEqual($expected + 5, $actual, $message);
    }

    /** 网络重试打同一个订单号：只扣一次钱、只开一次会员。 */
    public function testSameOrderNoChargesOnlyOnce(): void
    {
        $male = $this->makeMale(balance: 500);
        $orderNo = $this->orderNo();

        $first = $this->vip->buy($male, $this->planId(1), $orderNo);
        $second = $this->vip->buy($male, $this->planId(1), $orderNo);

        self::assertSame(110, $second['balance']);
        self::assertSame($first['expire_at'], $second['expire_at'], '第二次要返回第一次的结果');
        self::assertSame(1, VipOrder::query()->where('user_id', $male->id)->count());
        self::assertSame(1, CoinLog::query()->where('user_id', $male->id)->where('type', CoinLogType::VIP->value)->count());
    }

    // ---------- 场景 22：余额不足 ----------

    public function testScene22NotEnoughCoinsRollsEverythingBack(): void
    {
        $male = $this->makeMale(balance: 100);

        try {
            $this->vip->buy($male, $this->planId(1), $this->orderNo());
            self::fail('余额不足应该抛 20001');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::COIN_NOT_ENOUGH->value, $e->getResponse()->code);
            self::assertSame(['balance' => 100, 'needed' => 390], $e->getResponse()->data, 'P30 要靠这两个数算「还差多少」');
        }

        self::assertSame(100, make(CoinWalletService::class)->balanceOf($male->id), '余额一分不能少');
        self::assertFalse($male->refresh()->isVip(), '没买成就不能变成会员');
        self::assertNull($male->vip_expire_at);
        self::assertSame(0, VipOrder::query()->where('user_id', $male->id)->count(), '订单不能留下');
        self::assertSame(0, CoinLog::query()->where('user_id', $male->id)->where('type', CoinLogType::VIP->value)->count());
    }

    // ---------- 校验 ----------

    public function testFemaleCannotBuy(): void
    {
        $female = $this->makeFemale();

        try {
            $this->vip->buy($female, $this->planId(1), $this->orderNo());
            self::fail('女用户不该能开通会员');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }

        self::assertSame(0, VipOrder::query()->where('user_id', $female->id)->count());
    }

    public function testDisabledOrUnknownPlanIsRejected(): void
    {
        $male = $this->makeMale(balance: 500);
        $seasonPlan = VipPlan::query()->findOrFail($this->planId(2));
        $seasonPlan->update(['enabled' => 0]);
        foreach ([$this->planId(2), 999999] as $planId) {
            try {
                $this->vip->buy($male, $planId, $this->orderNo());
                self::fail('下架/不存在的套餐不该能买');
            } catch (ClBusinessException $e) {
                self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
            }
        }

        self::assertSame(500, make(CoinWalletService::class)->balanceOf($male->id));
    }

    // ---------- /vip/info ----------

    public function testInfoReturnsPlansBenefitsAndExpireAt(): void
    {
        $expire = Carbon::now()->addDays(10);
        $male = $this->makeMale(balance: 500, vipExpireAt: $expire->toDateTimeString());

        $info = $this->vip->info($male);

        self::assertTrue($info['is_vip']);
        self::assertSame($expire->toDateTimeString(), $info['expire_at']);
        self::assertSame(500, $info['balance']);
        self::assertCount(3, $info['plans']);
        self::assertSame(
            ['name' => '月卡', 'days' => 30, 'price_coins' => 390, 'price_yuan' => 39],
            [
                'name' => $info['plans'][0]['name'],
                'days' => $info['plans'][0]['days'],
                'price_coins' => $info['plans'][0]['price_coins'],
                'price_yuan' => $info['plans'][0]['price_yuan'],
            ],
            '设计图 12 的横幅写的是「¥39 / 月」，换算放服务端',
        );
        self::assertCount(4, $info['benefits']);
        self::assertSame('每日额外免费私信 10 条', $info['benefits'][3]['title'], '条数从配置取');

        // 配置改了，权益文案跟着变
        make(AppConfigService::class)->set('vip.daily_free_messages', 20);
        self::assertSame('每日额外免费私信 20 条', $this->vip->info($male)['benefits'][3]['title']);
    }

    public function testInfoHidesExpireAtAfterItPassed(): void
    {
        $male = $this->makeMale(balance: 0, vipExpireAt: Carbon::now()->subDay()->toDateTimeString());

        $info = $this->vip->info($male);

        self::assertFalse($info['is_vip']);
        self::assertNull($info['expire_at'], '过期了就不该给客户端一个过去的日期');
    }

    // ---------- 工具 ----------

    private function orderNo(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function planId(int $n): int
    {
        return $this->planIds[$n - 1];
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

    private function makeMale(int $balance = 0, ?string $vipExpireAt = null): User
    {
        $index = ++$this->sequence;
        $male = User::query()->create([
            'account' => 'vmale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '男' . $index,
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
            'vip_expire_at' => $vipExpireAt,
        ]);

        make(CoinWalletService::class)->createForUser($male->id);
        if ($balance > 0) {
            make(CoinWalletService::class)->increase($male->id, $balance, CoinLogType::RECHARGE, 'test', $male->id, '测试充值');
        }

        return $male->refresh();
    }

    private function makeFemale(): User
    {
        $index = ++$this->sequence;
        $female = User::query()->create([
            'account' => 'vfemale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女' . $index,
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);

        return $female->refresh();
    }

    private function resetConfig(): void
    {
        $config = make(AppConfigService::class);
        $config->set('coin.per_yuan', 10);
        $config->set('vip.daily_free_messages', 10);
        $config->set('vip.female_free_view', 1);
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
