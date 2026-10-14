<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PlatformIncome;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\RechargeOrder;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PlatformIncomeSource;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminFinanceService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 后台资金管理（docs/07 第 3.10~3.14 节、docs/06 第 8 节）。
 *
 * 直接对着 `06` 第 10 节的验收场景写：
 * 场景 31「客服给男用户充值 → 余额增加、有流水、有充值单、有操作人」、
 * 场景 32「给女用户充值金币 → 被拒」、场景 33「扣减金币不能扣成负数」。
 *
 * @internal
 * @coversNothing
 */
final class AdminFinanceServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminFinanceService $service;

    private AdminUser $operator;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminFinanceService::class);
        AdminUser::query()->where('username', 'kefu')->delete();
        $this->operator = AdminUser::create(['username' => 'kefu', 'password' => '123456', 'nickname' => '客服']);
    }

    protected function tearDown(): void
    {
        $this->clear();
        AdminUser::query()->where('username', 'kefu')->delete();
        parent::tearDown();
    }

    /** 场景 31。 */
    public function testRechargeMaleGivesCoinsLedgerAndOrder(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);

        $result = $this->service->recharge($this->operator, $male->id, 500, 5000, 'WECHAT', '首充');

        self::assertSame(500, $result['coins']);
        self::assertSame(500, $result['balance'], '余额当场增加');

        // 有流水：类型 RECHARGE、金额对、记了操作人
        $log = CoinLog::query()->where('user_id', $male->id)->firstOrFail();
        self::assertSame(CoinLogType::RECHARGE->value, (string) $log->type);
        self::assertSame(500, (int) $log->change);
        self::assertSame(500, (int) $log->balance_after);
        self::assertSame($this->operator->id, (int) $log->operator_id);

        // 有充值单：金币、实收（分）、渠道、操作人都记下了
        $order = RechargeOrder::query()->firstOrFail();
        self::assertSame($result['order_no'], (string) $order->order_no);
        self::assertSame(500, (int) $order->coins);
        self::assertSame(5000, (int) $order->amount_fen);
        self::assertSame('WECHAT', (string) $order->pay_channel);
        self::assertSame($this->operator->id, (int) $order->operator_id);

        // 累计充值跟着涨（钱包上那一列）
        self::assertSame(500, (int) CoinWallet::query()->where('user_id', $male->id)->value('total_recharge'));
    }

    /** 赠送（实收 0）也能充。 */
    public function testRechargeAllowsZeroAmountAsGift(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);

        $this->service->recharge($this->operator, $male->id, 100, 0, 'GIFT', '客服补偿');

        self::assertSame(100, (int) CoinWallet::query()->where('user_id', $male->id)->value('balance'));
        self::assertSame(0, (int) RechargeOrder::query()->value('amount_fen'));
    }

    /** 场景 32：给女用户充金币要被拒，而且**一分钱都不能动**。 */
    public function testRechargeFemaleIsRejected(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE);

        try {
            $this->service->recharge($this->operator, $female->id, 500, 5000, 'WECHAT', '');
            self::fail('女号充值应该被拒');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }

        self::assertSame(0, RechargeOrder::query()->count(), '被拒时不能留下充值单');
        self::assertSame(0, CoinLog::query()->count());
    }

    public function testRechargeRejectsNonPositiveCoinsAndBadChannel(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);

        foreach ([[0, 'WECHAT'], [-5, 'WECHAT'], [10, 'BITCOIN']] as [$coins, $channel]) {
            try {
                $this->service->recharge($this->operator, $male->id, $coins, 0, $channel, '');
                self::fail("coins={$coins} channel={$channel} 应该被拒");
            } catch (ClBusinessException) {
                self::assertSame(0, RechargeOrder::query()->count());
            }
        }
    }

    /** 场景 33：扣减不能扣成负数；失败时**连日志都不该留下**。 */
    public function testDeductCannotGoNegative(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, coins: 100);

        $this->service->deductCoins($this->operator, $male->id, 60, '误充退款');
        self::assertSame(40, (int) CoinWallet::query()->where('user_id', $male->id)->value('balance'));
        self::assertSame(1, AdminActionLog::query()->where('action', AdminActionLog::ACTION_COIN_DEDUCT)->count());

        try {
            $this->service->deductCoins($this->operator, $male->id, 999, '再扣一次');
            self::fail('余额不够应该被拒');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::COIN_NOT_ENOUGH->value, $e->getResponse()->code);
        }

        self::assertSame(40, (int) CoinWallet::query()->where('user_id', $male->id)->value('balance'), '余额没变');
        self::assertSame(1, AdminActionLog::query()->where('action', AdminActionLog::ACTION_COIN_DEDUCT)->count(), '失败那笔的日志要跟着回滚');
    }

    /** 调积分：正数补发、负数扣减，而且**补发不计入累计收益**（不然一次人工补发会把等级顶上去）。 */
    public function testAdjustPointsUpAndDownWithoutTouchingEarned(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, points: 1000, earned: 500);

        $this->service->adjustPoints($this->operator, $female->id, 200, '活动补发');
        $this->service->adjustPoints($this->operator, $female->id, -50, '误发扣回');

        self::assertSame(1150, (int) PointWallet::query()->where('user_id', $female->id)->value('balance'));
        self::assertSame(2, PointLog::query()->where('user_id', $female->id)->count());
        self::assertSame(
            500,
            (int) $female->refresh()->total_earned_points,
            '人工补发不算累计收益，等级不会被顶上去',
        );
    }

    public function testAdjustPointsRejectsZero(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE);

        $this->expectException(ClBusinessException::class);
        $this->service->adjustPoints($this->operator, $female->id, 0, '没变');
    }

    /** 想扣别人的金币但余额不够时，日志和流水都不会留（在同一个事务里）。 */
    public function testDeductRollsBackAdminLogWhenBalanceIsShort(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, coins: 10);

        try {
            $this->service->deductCoins($this->operator, $male->id, 100, '扣不动');
        } catch (ClBusinessException) {
            // 预期
        }

        self::assertSame(0, AdminActionLog::query()->count());
        self::assertSame(0, CoinLog::query()->count());
    }

    public function testRechargeListCarriesTotals(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $this->service->recharge($this->operator, $male->id, 100, 1000, 'WECHAT', '');
        $this->service->recharge($this->operator, $male->id, 200, 2000, 'ALIPAY', '');

        $result = $this->service->rechargeList([], 1, 20);

        self::assertSame(2, $result['total']);
        self::assertSame(300, $result['coins'], '底部合计：金币');
        self::assertSame(3000, $result['amount_fen'], '底部合计：实收（分）');
        self::assertSame('kefu', $result['list'][0]['operator_name'], '列表要带上操作人');
        self::assertSame('阿宁', $result['list'][0]['user_nickname']);
    }

    public function testWalletLogsFilterByUserAndType(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $this->service->recharge($this->operator, $male->id, 100, 1000, 'WECHAT', '');
        $this->service->deductCoins($this->operator, $male->id, 30, '扣一点');

        $coins = $this->service->coinLogs(['user_id' => $male->id], 1, 20);
        self::assertSame(2, $coins['total']);
        self::assertSame('RECHARGE', $coins['list'][1]['type']);
        self::assertSame('ADMIN_DEDUCT', $coins['list'][0]['type']);

        $onlyRecharge = $this->service->coinLogs(['user_id' => $male->id, 'type' => 'RECHARGE'], 1, 20);
        self::assertSame(1, $onlyRecharge['total']);
    }

    /** 积分流水要比金币流水多两列（冻结变动 / 冻结后），提现那几笔才看得清。 */
    public function testPointLogsCarryFrozenColumns(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, points: 100);
        $this->service->adjustPoints($this->operator, $female->id, 50, '补发');

        $row = $this->service->pointLogs(['user_id' => $female->id], 1, 20)['list'][0];

        self::assertArrayHasKey('frozen_change', $row);
        self::assertArrayHasKey('frozen_after', $row);
        self::assertSame(0, $row['frozen_change']);
    }

    /** 平台收入按天汇总，毛收入 = 金币 ÷ 汇率 − 积分 ÷ 汇率（docs/04 第 4 节）。 */
    public function testIncomeDailyAggregatesAndComputesGrossIncome(): void
    {
        $today = date('Y-m-d');
        $this->makeIncome($today, PlatformIncomeSource::MESSAGE, coins: 100, points: 40);
        $this->makeIncome($today, PlatformIncomeSource::GIFT, coins: 50, points: 17);
        $this->makeIncome($today, PlatformIncomeSource::VIP, coins: 390, points: 0);

        $result = $this->service->incomes($today, $today, 10, 100);

        self::assertCount(1, $result['list']);
        $day = $result['list'][0];
        self::assertSame($today, $day['date']);
        self::assertSame(100, (int) $day['message_coins']);
        self::assertSame(50, (int) $day['gift_coins']);
        self::assertSame(390, (int) $day['vip_coins']);
        self::assertSame(540, (int) $day['coins']);
        self::assertSame(57, (int) $day['points']);
        // 540 金币 ÷ 10 = 54 元；57 积分 ÷ 100 = 0.57 元 → 毛收入 53.43 元 = 5343 分
        self::assertSame(5343, (int) $day['income_fen']);
        self::assertSame(5343, $result['total_income_fen']);
    }

    public function testIncomeRangeExcludesOtherDays(): void
    {
        $this->makeIncome(date('Y-m-d', strtotime('-10 days')), PlatformIncomeSource::MESSAGE, 999, 0);
        $this->makeIncome(date('Y-m-d'), PlatformIncomeSource::MESSAGE, 100, 0);

        $result = $this->service->incomes(date('Y-m-d', strtotime('-1 day')), date('Y-m-d'), 10, 100);

        self::assertCount(1, $result['list']);
        self::assertSame(100, (int) $result['list'][0]['coins']);
    }

    // ---------- 工具 ----------

    private function makeIncome(string $date, PlatformIncomeSource $source, int $coins, int $points): void
    {
        PlatformIncome::query()->create([
            'source' => $source->value,
            'ref_id' => ++$this->sequence,
            'payer_id' => 1,
            'receiver_id' => 2,
            'coins' => $coins,
            'receiver_points' => $points,
            'income_date' => $date,
        ]);
    }

    private function makeUser(int $gender, int $coins = 0, int $points = 0, int $earned = 0): User
    {
        $phone = '13300000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $gender === User::GENDER_MALE ? '阿宁' : '晚晴',
            'avatar' => 'https://cdn.example.com/a.jpg',
            'total_earned_points' => $earned,
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id, 'balance' => $coins, 'total_recharge' => 0]);
        PointWallet::query()->create(['user_id' => $user->id, 'balance' => $points, 'frozen' => 0]);

        return $user;
    }

    private function clear(): void
    {
        RechargeOrder::query()->delete();
        PlatformIncome::query()->delete();
        AdminActionLog::query()->delete();
        CoinLog::query()->delete();
        PointLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
