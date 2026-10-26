<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Withdraw;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Notice;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Model\Cl\WithdrawAccount;
use App\Model\Cl\WithdrawOrder;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\PointLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use App\Service\Cl\Withdraw\WithdrawService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 提现（docs/06 第 9 节 + 第 10 节场景 23~30）。
 *
 * 积分那本账靠 `PointWalletService`，这里盯的是**流程和单据**：
 * 谁能提、能提多少、冻结/退回/结算三笔流水对不对、状态能不能乱走。
 *
 * @internal
 * @coversNothing
 */
final class WithdrawServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private WithdrawService $service;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->resetConfig();

        $this->service = make(WithdrawService::class);
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 场景 27：申请后可用减少、冻结增加 ----------

    public function testScene27ApplyFreezesPointsAndWritesOrder(): void
    {
        $female = $this->makeFemale(points: 5000);

        $result = $this->service->apply($female, 50, 'ALIPAY', 'zhangsan@example.com');

        self::assertSame('待审核', $result['status_text']);
        self::assertSame(5000, $result['amount_fen']);
        self::assertSame(5000, $result['points'], '50 元 × 100 积分/元');
        self::assertSame(0, $result['fee_fen'], '手续费默认 0');
        self::assertSame(5000, $result['actual_fen']);
        self::assertSame(0, $result['balance'], '可用从 5000 减到 0');
        self::assertSame(5000, $result['frozen'], '冻结从 0 加到 5000');

        $order = WithdrawOrder::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame(WithdrawOrder::STATUS_PENDING, (int) $order->status);
        self::assertSame($result['order_no'], $order->order_no);
        self::assertSame('张三', $order->account_name, '收款人取实名姓名');

        // 流水：可用 −5000、冻结 +5000
        $log = PointLog::query()->where('user_id', $female->id)->where('type', PointLogType::WITHDRAW_FREEZE->value)->firstOrFail();
        self::assertSame(-5000, (int) $log->change);
        self::assertSame(5000, (int) $log->frozen_change);
        self::assertSame((int) $order->id, (int) $log->ref_id);
    }

    public function testFeeIsFloorOfFenTimesRate(): void
    {
        make(AppConfigService::class)->set('withdraw.fee_rate', 500);
        $female = $this->makeFemale(points: 100000);

        // 50 元 = 5000 分，5% = 250 分 = 2.50 元，实到 4750 分
        $result = $this->service->apply($female, 50, 'WECHAT', 'wx-001');

        self::assertSame(250, $result['fee_fen']);
        self::assertSame(4750, $result['actual_fen']);
        self::assertSame(5000, $result['points'], '冻结的还是全额 —— 手续费是平台收，不是少冻');
    }

    public function testAccountIsSavedForNextTime(): void
    {
        $female = $this->makeFemale(points: 100000);

        $this->service->apply($female, 50, 'BANK', '6222020000000000000', '招商银行');

        $saved = WithdrawAccount::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame('BANK', $saved->account_type);
        self::assertSame('招商银行', $saved->bank_name);

        $info = $this->service->info($female);
        self::assertSame('6222020000000000000', $info['account']['account_no']);
    }

    // ---------- 场景 23~26：四种被拒 ----------

    public function testScene23NotRealNameIsRejected(): void
    {
        $female = $this->makeFemale(points: 100000, realNamePassed: false);

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '请先完成实名认证', function () use ($female): void {
            $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        });

        self::assertSame(0, WithdrawOrder::query()->where('user_id', $female->id)->count());
        self::assertSame(100000, (int) make(PointWalletService::class)->summaryOf($female->id)['balance'], '一分没动');
    }

    public function testRealNameSwitchOffAllowsApply(): void
    {
        make(AppConfigService::class)->set('withdraw.need_real_name', 0);
        $female = $this->makeFemale(points: 100000, realNamePassed: false);

        // 开关关掉之后不用实名也能提（收款人姓名会是空串）
        $result = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');

        self::assertSame('待审核', $result['status_text']);
    }

    public function testScene24AmountNotInTiersIsRejected(): void
    {
        $female = $this->makeFemale(points: 100000);

        foreach ([30, 60, 1000] as $bad) {
            $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '请选择提现档位里的金额', function () use ($female, $bad): void {
                $this->service->apply($female, $bad, 'ALIPAY', 'a@b.com');
            });
        }

        self::assertSame(0, WithdrawOrder::query()->where('user_id', $female->id)->count());
    }

    public function testScene25NotEnoughPointsReturns20002(): void
    {
        $female = $this->makeFemale(points: 4000);

        try {
            $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
            self::fail('积分不够应该抛 20002');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::POINT_NOT_ENOUGH->value, $e->getResponse()->code);
            self::assertSame(['balance' => 4000, 'needed' => 5000], $e->getResponse()->data);
        }

        // 整单回滚：没有单据，也没有冻结流水
        self::assertSame(0, WithdrawOrder::query()->where('user_id', $female->id)->count());
        self::assertSame(4000, (int) make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    public function testScene26SecondApplySameDayIsRejected(): void
    {
        $female = $this->makeFemale(points: 100000);
        $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '今天已经申请过了，请明天再来', function () use ($female): void {
            $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        });

        self::assertSame(1, WithdrawOrder::query()->where('user_id', $female->id)->count());
    }

    public function testRejectedOrderStillCountsTowardDailyLimit(): void
    {
        $female = $this->makeFemale(points: 100000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $this->service->reject($this->orderId($order['order_no']), reviewerId: 9, reason: '账号有误');

        // 驳回的也算次数（docs/06 第 9.1 节第 3 步）
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($female): void {
            $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        });
    }

    public function testDailyLimitZeroMeansUnlimited(): void
    {
        make(AppConfigService::class)->set('withdraw.daily_limit', 0);
        $female = $this->makeFemale(points: 100000);

        $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');

        self::assertSame(2, WithdrawOrder::query()->where('user_id', $female->id)->count());
    }

    // ---------- 场景 28~30：审核三个动作 ----------

    public function testScene28RejectRefundsPoints(): void
    {
        $female = $this->makeFemale(points: 5000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        self::assertSame(0, (int) make(PointWalletService::class)->summaryOf($female->id)['balance']);

        $this->service->reject($this->orderId($order['order_no']), reviewerId: 9, reason: '收款账号和实名不符');

        $wallet = make(PointWalletService::class)->summaryOf($female->id);
        self::assertSame(5000, (int) $wallet['balance'], '积分原路退回');
        self::assertSame(0, (int) $wallet['frozen']);

        $row = WithdrawOrder::query()->where('order_no', $order['order_no'])->firstOrFail();
        self::assertSame(WithdrawOrder::STATUS_REJECTED, (int) $row->status);
        self::assertSame('收款账号和实名不符', $row->reject_reason);
        self::assertSame(9, (int) $row->reviewer_id);

        $log = PointLog::query()->where('user_id', $female->id)->where('type', PointLogType::WITHDRAW_REFUND->value)->firstOrFail();
        self::assertSame(5000, (int) $log->change);
        self::assertSame(-5000, (int) $log->frozen_change);

        $notice = Notice::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame('提现被驳回', $notice->title);
        self::assertStringContainsString('积分已退回', $notice->content);
    }

    public function testScene29MarkPaidSettlesFrozenAndAddsTotalWithdrawn(): void
    {
        $female = $this->makeFemale(points: 5000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $orderId = $this->orderId($order['order_no']);

        $this->service->approve($orderId, reviewerId: 9);
        self::assertSame(
            5000,
            (int) make(PointWalletService::class)->summaryOf($female->id)['frozen'],
            '通过之后积分仍然冻结',
        );

        $this->service->markPaid($orderId, payerId: 7, payProof: 'PAY20261014001');

        $wallet = PointWallet::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame(0, (int) $wallet->frozen, '打款后冻结清零');
        self::assertSame(5000, (int) $wallet->total_withdrawn, '累计提现增加');
        self::assertSame(0, (int) $wallet->balance);

        $row = WithdrawOrder::query()->whereKey($orderId)->firstOrFail();
        self::assertSame(WithdrawOrder::STATUS_PAID, (int) $row->status);
        self::assertSame('PAY20261014001', $row->pay_proof);
        self::assertSame(7, (int) $row->payer_id);
        self::assertNotNull($row->paid_at);

        $log = PointLog::query()->where('user_id', $female->id)->where('type', PointLogType::WITHDRAW_PAID->value)->firstOrFail();
        self::assertSame(0, (int) $log->change);
        self::assertSame(-5000, (int) $log->frozen_change);

        self::assertSame('提现已打款', Notice::query()->where('user_id', $female->id)->orderByDesc('id')->value('title'));
    }

    public function testScene30PaidOrderCannotBeRejected(): void
    {
        $female = $this->makeFemale(points: 5000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $orderId = $this->orderId($order['order_no']);
        $this->service->approve($orderId, reviewerId: 9);
        $this->service->markPaid($orderId, payerId: 7, payProof: 'PAY-1');

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($orderId): void {
            $this->service->reject($orderId, reviewerId: 9, reason: '想反悔');
        });

        // 冻结已经扣掉了，再退就凭空多出积分
        self::assertSame(0, (int) PointWallet::query()->where('user_id', $female->id)->value('balance'));
        self::assertSame(0, PointLog::query()->where('user_id', $female->id)->where('type', PointLogType::WITHDRAW_REFUND->value)->count());
    }

    public function testScene30RejectedOrderCannotBePaid(): void
    {
        $female = $this->makeFemale(points: 5000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $orderId = $this->orderId($order['order_no']);
        $this->service->reject($orderId, reviewerId: 9, reason: '账号有误');

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($orderId): void {
            $this->service->markPaid($orderId, payerId: 7, payProof: 'PAY-2');
        });

        self::assertSame(WithdrawOrder::STATUS_REJECTED, (int) WithdrawOrder::query()->whereKey($orderId)->value('status'));
    }

    public function testApproveOnlyFromPendingAndPaidOnlyFromApproved(): void
    {
        $female = $this->makeFemale(points: 5000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $orderId = $this->orderId($order['order_no']);

        // 没通过审核不能直接打款
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '这张提现单还没通过审核，不能打款', function () use ($orderId): void {
            $this->service->markPaid($orderId, payerId: 7, payProof: 'PAY-3');
        });

        $this->service->approve($orderId, reviewerId: 9);
        // 已经通过的不再重复通过
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '这张提现单不是待审核状态', function () use ($orderId): void {
            $this->service->approve($orderId, reviewerId: 9);
        });
    }

    public function testApproveAndPaidNeedOperatorInput(): void
    {
        $female = $this->makeFemale(points: 5000);
        $order = $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $orderId = $this->orderId($order['order_no']);

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '驳回必须填写原因', function () use ($orderId): void {
            $this->service->reject($orderId, reviewerId: 9, reason: '  ');
        });

        $this->service->approve($orderId, reviewerId: 9);
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '请填写打款流水号或备注', function () use ($orderId): void {
            $this->service->markPaid($orderId, payerId: 7, payProof: '');
        });
    }

    // ---------- 页面数据 ----------

    public function testInfoShowsTiersAndTodayQuota(): void
    {
        $female = $this->makeFemale(points: 12000);

        $info = $this->service->info($female);

        self::assertSame(120, $info['amount_yuan'], '12000 积分 ÷ 100 = 120 元');
        self::assertSame(12000, $info['points']);
        self::assertCount(4, $info['tiers']);
        // 50 / 100 提得起，200 / 500 不够
        self::assertSame([true, true, false, false], array_column($info['tiers'], 'enabled'));
        self::assertSame(5000, $info['tiers'][0]['points']);
        self::assertSame(1, $info['daily_limit']);
        self::assertSame(0, $info['today_count']);
        self::assertSame(1, $info['remaining_today']);
        self::assertTrue($info['real_name_passed']);
        self::assertSame('提交后 1~3 个工作日内审核打款', $info['notice']);
    }

    /** 档位里配了低于最低提现金额的金额时：置灰，提交也要给明确提示（客户 2026-10-26 反馈）。 */
    public function testTierBelowMinYuanIsDisabledAndRejectedWithClearMessage(): void
    {
        $config = make(AppConfigService::class);
        $config->set('withdraw.tiers', [1, 50, 100]);
        $config->set('withdraw.min_yuan', 50);

        $female = $this->makeFemale(points: 10000);
        $info = $this->service->info($female);
        $one = array_values(array_filter(
            $info['tiers'],
            static fn (array $tier): bool => $tier['yuan'] === 1,
        ))[0];
        self::assertFalse($one['enabled'], '低于最低金额的档位不能选');

        try {
            $this->service->apply($female, 1, 'ALIPAY', 'a@example.com');
            self::fail('低于最低金额应该被拒绝');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
            self::assertStringContainsString('最低提现金额', (string) $e->getResponse()->message);
        }
    }

    public function testRecordsAreNewestFirst(): void
    {
        make(AppConfigService::class)->set('withdraw.daily_limit', 0);
        $female = $this->makeFemale(points: 100000);
        $this->service->apply($female, 50, 'ALIPAY', 'a@b.com');
        $this->service->apply($female, 100, 'ALIPAY', 'a@b.com');

        $records = $this->service->records($female, 1);

        self::assertSame(2, $records['total']);
        self::assertSame(10000, $records['list'][0]['amount_fen'], '最新的在最前');
        self::assertSame('待审核', $records['list'][0]['status_text']);
    }

    public function testMaleCannotWithdraw(): void
    {
        $male = $this->makeMale();

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '提现只有女用户有', function () use ($male): void {
            $this->service->apply($male, 50, 'ALIPAY', 'a@b.com');
        });
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($male): void {
            $this->service->info($male);
        });
    }

    public function testBadAccountInputIsRejected(): void
    {
        $female = $this->makeFemale(points: 100000);

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '收款方式不正确', function () use ($female): void {
            $this->service->apply($female, 50, 'PAYPAL', 'a@b.com');
        });
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '请填写收款账号', function () use ($female): void {
            $this->service->apply($female, 50, 'ALIPAY', '  ');
        });
        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, '请填写开户行', function () use ($female): void {
            $this->service->apply($female, 50, 'BANK', '6222020000000000000');
        });
    }

    // ---------- 工具 ----------

    private function orderId(string $orderNo): int
    {
        return (int) WithdrawOrder::query()->where('order_no', $orderNo)->value('id');
    }

    /**
     * 断言抛出的业务码（以及可选的一条文案）。两种写法都收：
     * `assertBizCode($code, fn)` 和 `assertBizCode($code, '文案', fn)`。
     *
     * @param callable(): void|string $second
     * @param null|callable(): void $third
     */
    private function assertBizCode(BizCode $expected, callable|string $second, ?callable $third = null): void
    {
        $message = $second instanceof \Closure ? null : $second;
        /** @var callable(): void $block */
        $block = $second instanceof \Closure ? $second : $third;

        try {
            $block();
            self::fail('应该抛业务错误 ' . $expected->value);
        } catch (ClBusinessException $e) {
            self::assertSame($expected->value, $e->getResponse()->code);
            if ($message !== null) {
                self::assertSame($message, $e->getResponse()->message);
            }
        }
    }

    private function resetConfig(): void
    {
        $config = make(AppConfigService::class);
        $config->set('point.per_yuan', 100);
        $config->set('withdraw.tiers', [50, 100, 200, 500]);
        $config->set('withdraw.min_yuan', 50);
        $config->set('withdraw.daily_limit', 1);
        $config->set('withdraw.fee_rate', 0);
        $config->set('withdraw.need_real_name', 1);
        $config->set('withdraw.notice', '提交后 1~3 个工作日内审核打款');
    }

    private function makeFemale(int $points, bool $realNamePassed = true): User
    {
        $index = ++$this->sequence;
        $female = User::query()->create([
            'account' => 'wdfemale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女' . $index,
            'level' => 1,
            'real_name_status' => $realNamePassed ? User::REAL_NAME_PASSED : User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);
        make(CoinWalletService::class)->createForUser($female->id);
        if ($points > 0) {
            make(PointWalletService::class)->increase($female->id, $points, PointLogType::MESSAGE_INCOME, 'test', $female->id, '测试收益');
        }
        if ($realNamePassed) {
            RealName::query()->create([
                'user_id' => $female->id,
                'real_name' => '张三',
                'id_card_encrypted' => 'x',
                'id_card_mask' => '110***********1233',
                'id_card_hash' => hash('sha256', '110101199003071233'),
                'status' => RealName::STATUS_PASSED,
            ]);
        }

        return $female->refresh();
    }

    private function makeMale(): User
    {
        $index = ++$this->sequence;
        $male = User::query()->create([
            'account' => 'wdmale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '男' . $index,
            'level' => 1,
            'status' => User::STATUS_NORMAL,
        ]);

        make(CoinWalletService::class)->createForUser($male->id);

        return $male->refresh();
    }

    private function clearAll(): void
    {
        Notice::query()->delete();
        WithdrawAccount::query()->delete();
        WithdrawOrder::query()->delete();
        RealName::query()->delete();
        PointLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
