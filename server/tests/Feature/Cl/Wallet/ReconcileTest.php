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
use App\Repository\Cl\CoinLogRepository;
use App\Repository\Cl\PointLogRepository;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * docs/06 第 10 节场景 34（对账）：
 *   金币钱包 balance = 该用户全部金币流水 change 之和；
 *   积分钱包 balance = 全部积分流水 change 之和，frozen = 全部积分流水 frozen_change 之和。
 *
 * @internal
 * @coversNothing
 */
final class ReconcileTest extends TestCase
{
    use RunTestsInCoroutine;

    private const USER_ID = 10002;

    private CoinWalletService $coins;

    private PointWalletService $points;

    private CoinLogRepository $coinLogs;

    private PointLogRepository $pointLogs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearWallets();

        $container = ApplicationContext::getContainer();
        $this->coins = $container->get(CoinWalletService::class);
        $this->points = $container->get(PointWalletService::class);
        $this->coinLogs = $container->get(CoinLogRepository::class);
        $this->pointLogs = $container->get(PointLogRepository::class);

        $this->coins->createForUser(self::USER_ID);
        $this->points->createForUser(self::USER_ID);
    }

    protected function tearDown(): void
    {
        $this->clearWallets();
        parent::tearDown();
    }

    public function testReconcileAfter100RandomOperations(): void
    {
        for ($refId = 1; $refId <= 100; ++$refId) {
            $this->randomOperation($refId);
        }

        self::assertSame(
            $this->coinLogs->sumChangeByUser(self::USER_ID),
            $this->coins->balanceOf(self::USER_ID),
            '金币余额必须等于全部金币流水 change 之和'
        );

        $sums = $this->pointLogs->sumByUser(self::USER_ID);
        $summary = $this->points->summaryOf(self::USER_ID);
        self::assertSame($sums['change'], $summary['balance'], '积分余额必须等于全部积分流水 change 之和');
        self::assertSame($sums['frozen_change'], $summary['frozen'], '冻结积分必须等于全部积分流水 frozen_change 之和');
    }

    private function randomOperation(int $refId): void
    {
        switch (random_int(1, 6)) {
            case 1:
                $this->safe(fn () => $this->coins->increase(self::USER_ID, random_int(1, 50), CoinLogType::RECHARGE, 'recharge_order', $refId, '随机充值'));
                break;
            case 2:
                $this->safe(fn () => $this->coins->decrease(self::USER_ID, random_int(1, 50), CoinLogType::MESSAGE, 'message', $refId, '随机私信'));
                break;
            case 3:
                $this->safe(fn () => $this->points->increase(self::USER_ID, random_int(1, 200), PointLogType::MESSAGE_INCOME, 'message', $refId, '随机收益'));
                break;
            case 4:
                $this->safe(fn () => $this->points->decrease(self::USER_ID, random_int(1, 100), PointLogType::ADMIN_ADJUST, 'admin', $refId, '随机扣减'));
                break;
            case 5:
                $this->safe(fn () => $this->points->freeze(self::USER_ID, random_int(1, 100), 'withdraw_order', $refId, '随机冻结'));
                break;
            default:
                $this->safe(fn () => $this->points->settleFrozen(self::USER_ID, random_int(1, 100), 'withdraw_order', $refId, '随机结算'));
                break;
        }
    }

    private function safe(callable $operation): void
    {
        try {
            $operation();
        } catch (ClBusinessException) {
            // 余额/冻结不足时跳过，对账结果不受影响
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
