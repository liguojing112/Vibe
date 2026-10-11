<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Wallet;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PointLogType;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 钱包服务（docs/06 第 1 节、docs/04 第 4 节）。
 *
 * @internal
 * @coversNothing
 */
final class WalletServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private const USER_ID = 10001;

    private CoinWalletService $coins;

    private PointWalletService $points;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWallets();

        $container = ApplicationContext::getContainer();
        $this->coins = $container->get(CoinWalletService::class);
        $this->points = $container->get(PointWalletService::class);

        $this->coins->createForUser(self::USER_ID);
        $this->points->createForUser(self::USER_ID);
    }

    protected function tearDown(): void
    {
        $this->clearWallets();
        parent::tearDown();
    }

    public function testCoinIncreaseAndDecreaseKeepsBalanceAfterCorrect(): void
    {
        $this->coins->increase(self::USER_ID, 100, CoinLogType::RECHARGE, 'recharge_order', 1, '客服充值');
        self::assertSame(100, $this->coins->balanceOf(self::USER_ID));

        $this->coins->decrease(self::USER_ID, 30, CoinLogType::MESSAGE, 'message', 1, '私信 · 小满');
        self::assertSame(70, $this->coins->balanceOf(self::USER_ID));

        $logs = CoinLog::query()->where('user_id', self::USER_ID)->orderBy('id')->get();
        self::assertCount(2, $logs);
        self::assertSame(100, $logs[0]->change);
        self::assertSame(100, $logs[0]->balance_after);
        self::assertSame(-30, $logs[1]->change);
        self::assertSame(70, $logs[1]->balance_after);
    }

    public function testCoinDecreaseNotEnoughReturns20001AndRollsBack(): void
    {
        $this->coins->increase(self::USER_ID, 4, CoinLogType::RECHARGE, 'recharge_order', 1, '客服充值');

        try {
            $this->coins->decrease(self::USER_ID, 5, CoinLogType::MESSAGE, 'message', 1, '私信');
            self::fail('余额不足应当抛业务异常');
        } catch (ClBusinessException $e) {
            self::assertSame(20001, $e->getResponse()->code);
            self::assertSame(4, $e->getResponse()->data['balance']);
            self::assertSame(5, $e->getResponse()->data['needed']);
        }

        self::assertSame(4, $this->coins->balanceOf(self::USER_ID));
        // 失败的扣款不能留下流水
        self::assertSame(1, CoinLog::query()->where('user_id', self::USER_ID)->count());
    }

    public function testCoinDuplicateRefIsRecordedOnlyOnce(): void
    {
        $this->coins->increase(self::USER_ID, 100, CoinLogType::RECHARGE, 'recharge_order', 9, '客服充值');
        $this->coins->increase(self::USER_ID, 100, CoinLogType::RECHARGE, 'recharge_order', 9, '客服充值');

        self::assertSame(100, $this->coins->balanceOf(self::USER_ID));
        self::assertSame(1, CoinLog::query()->where('user_id', self::USER_ID)->count());
    }

    public function testCoinTotalsTrackRechargeAndSpending(): void
    {
        $this->coins->increase(self::USER_ID, 100, CoinLogType::RECHARGE, 'recharge_order', 1, '客服充值');
        $this->coins->increase(self::USER_ID, 10, CoinLogType::TASK, 'task_log', 1, '任务奖励');
        $this->coins->decrease(self::USER_ID, 30, CoinLogType::GIFT, 'gift_record', 1, '礼物 · 花朵');
        $this->coins->decrease(self::USER_ID, 5, CoinLogType::ADMIN_DEDUCT, 'admin', 1, '后台扣减');

        $wallet = CoinWallet::query()->where('user_id', self::USER_ID)->first();
        self::assertSame(100, $wallet->total_recharge);  // 只有 RECHARGE 计入
        self::assertSame(30, $wallet->total_spent);      // 只有 GIFT 这次计入（ADMIN_DEDUCT 不计）
        self::assertSame(75, $wallet->balance);
    }

    public function testPointWithdrawFlowMatchesDocTable(): void
    {
        $this->points->increase(self::USER_ID, 500, PointLogType::MESSAGE_INCOME, 'message', 1, '私信收益');
        self::assertSame(['balance' => 500, 'frozen' => 0], $this->points->summaryOf(self::USER_ID));

        // 申请提现：可用 −N，冻结 +N
        $this->points->freeze(self::USER_ID, 200, 'withdraw_order', 1, '提现冻结');
        self::assertSame(['balance' => 300, 'frozen' => 200], $this->points->summaryOf(self::USER_ID));

        // 驳回：冻结 −N，可用 +N
        $this->points->unfreeze(self::USER_ID, 200, 'withdraw_order', 1, '提现驳回退回');
        self::assertSame(['balance' => 500, 'frozen' => 0], $this->points->summaryOf(self::USER_ID));

        // 再申请并打款：冻结 −N，可用不变
        $this->points->freeze(self::USER_ID, 200, 'withdraw_order', 2, '提现冻结');
        $this->points->settleFrozen(self::USER_ID, 200, 'withdraw_order', 2, '提现打款');
        self::assertSame(['balance' => 300, 'frozen' => 0], $this->points->summaryOf(self::USER_ID));

        $wallet = PointWallet::query()->where('user_id', self::USER_ID)->first();
        self::assertSame(200, $wallet->total_withdrawn);

        $freezeLog = PointLog::query()->where('type', 'WITHDRAW_FREEZE')->where('ref_id', 2)->first();
        self::assertSame(-200, $freezeLog->change);
        self::assertSame(200, $freezeLog->frozen_change);
        self::assertSame(300, $freezeLog->balance_after);
        self::assertSame(200, $freezeLog->frozen_after);

        $paidLog = PointLog::query()->where('type', 'WITHDRAW_PAID')->first();
        self::assertSame(0, $paidLog->change);
        self::assertSame(-200, $paidLog->frozen_change);
        self::assertSame(300, $paidLog->balance_after);
        self::assertSame(0, $paidLog->frozen_after);
    }

    public function testPointFreezeNotEnoughReturns20002(): void
    {
        $this->points->increase(self::USER_ID, 10, PointLogType::TASK, 'task_log', 1, '任务奖励');

        try {
            $this->points->freeze(self::USER_ID, 50, 'withdraw_order', 3, '提现冻结');
            self::fail('积分不足应当抛业务异常');
        } catch (ClBusinessException $e) {
            self::assertSame(20002, $e->getResponse()->code);
        }

        self::assertSame(['balance' => 10, 'frozen' => 0], $this->points->summaryOf(self::USER_ID));
    }

    /**
     * docs/06 第 10 节场景 8：余额 10，并发扣 3 笔 ×5，只能成功 2 笔，余额不为负。
     */
    public function testConcurrentDecreaseNeverGoesNegative(): void
    {
        $this->coins->increase(self::USER_ID, 10, CoinLogType::RECHARGE, 'recharge_order', 1, '客服充值');

        $results = parallel([
            fn () => $this->tryDecrease(1),
            fn () => $this->tryDecrease(2),
            fn () => $this->tryDecrease(3),
        ]);

        $succeeded = \count(array_filter($results, static fn ($ok): bool => $ok === true));

        self::assertSame(2, $succeeded, '并发扣减应当只成功 2 笔');
        self::assertSame(0, $this->coins->balanceOf(self::USER_ID));
        self::assertGreaterThanOrEqual(0, $this->coins->balanceOf(self::USER_ID));
    }

    private function tryDecrease(int $refId): bool
    {
        try {
            $this->coins->decrease(self::USER_ID, 5, CoinLogType::MESSAGE, 'message', $refId, '并发私信');
            return true;
        } catch (ClBusinessException) {
            return false;
        }
    }

    private function clearWallets(): void
    {
        CoinLog::query()->delete();
        PointLog::query()->delete();
        CoinWallet::query()->delete();
        PointWallet::query()->delete();
    }
}
