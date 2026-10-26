<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Pay;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\RechargeOrder;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Pay\EpayService;
use App\Service\Cl\Pay\RechargePayService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * App 在线充值（万世码支付 / 支付宝）。
 *
 * 重点守住三件事：
 * 1. 回调验签 + 金额核对，伪造/串单不认；
 * 2. 到账只加一次金币（重复回调安全）；
 * 3. 没配好商户号时不允许下单。
 *
 * @internal
 * @coversNothing
 */
final class RechargePayServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private RechargePayService $service;

    private AppConfigService $config;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->config = make(AppConfigService::class);
        $this->service = make(RechargePayService::class);
        $this->configurePayment(true);
    }

    protected function tearDown(): void
    {
        $this->configurePayment(false);
        $this->clear();
        parent::tearDown();
    }

    public function testNotifyMarksPaidAndAddsCoinsOnlyOnce(): void
    {
        $user = $this->male();
        $order = $this->pendingOrder($user, 60, 600);

        self::assertTrue($this->service->handleNotify($this->notify($order)));

        $fresh = RechargeOrder::query()->findOrFail($order->id);
        self::assertSame(RechargeOrder::STATUS_PAID, (int) $fresh->status);
        self::assertNotNull($fresh->paid_at);
        self::assertSame('T-1001', (string) $fresh->trade_no);

        self::assertSame(60, (int) CoinWallet::query()->where('user_id', $user->id)->value('balance'));
        self::assertSame(60, (int) CoinWallet::query()->where('user_id', $user->id)->value('total_recharge'));

        $log = CoinLog::query()->where('user_id', $user->id)->firstOrFail();
        self::assertSame(CoinLogType::RECHARGE->value, (string) $log->type);
        self::assertSame(60, (int) $log->change);
        self::assertSame('recharge_order', (string) $log->ref_type);
        self::assertSame((int) $order->id, (int) $log->ref_id);

        // 码支付重复通知：仍然回 success，但绝不能重复加币
        self::assertTrue($this->service->handleNotify($this->notify($order)));
        self::assertSame(60, (int) CoinWallet::query()->where('user_id', $user->id)->value('balance'));
        self::assertSame(1, CoinLog::query()->where('user_id', $user->id)->count());
    }

    public function testNotifyRejectsAmountMismatch(): void
    {
        $user = $this->male();
        $order = $this->pendingOrder($user, 60, 600);

        // 签名是对的，但金额和下单时不一致（伪造或串单）
        self::assertFalse($this->service->handleNotify($this->notify($order, '5.00')));

        self::assertSame(
            RechargeOrder::STATUS_PENDING,
            (int) RechargeOrder::query()->findOrFail($order->id)->status,
        );
        self::assertSame(0, (int) CoinWallet::query()->where('user_id', $user->id)->value('balance'));
        self::assertSame(0, CoinLog::query()->where('user_id', $user->id)->count());
    }

    public function testNotifyRejectsInvalidSignature(): void
    {
        $user = $this->male();
        $order = $this->pendingOrder($user, 60, 600);

        $params = $this->notify($order);
        $params['sign'] = 'bad-sign';

        self::assertFalse($this->service->handleNotify($params));
        self::assertSame(
            RechargeOrder::STATUS_PENDING,
            (int) RechargeOrder::query()->findOrFail($order->id)->status,
        );
    }

    public function testCreateRejectedWhenPaymentSwitchedOff(): void
    {
        $this->configurePayment(false);
        $user = $this->male();

        try {
            $this->service->create($user, 0, '127.0.0.1');
            self::fail('没开在线支付不应该能下单');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::FEATURE_NOT_OPEN->value, $e->getResponse()->code);
        }

        self::assertSame(0, RechargeOrder::query()->count());
    }

    public function testCreateRejectedForFemale(): void
    {
        $female = $this->female();

        try {
            $this->service->create($female, 0, '127.0.0.1');
            self::fail('女用户没有金币钱包，不应该能充值');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }
    }

    // ---------- 工具 ----------

    private function configurePayment(bool $enabled): void
    {
        $this->config->set('pay.enabled', $enabled ? 1 : 0);
        $this->config->set('pay.epay_url', 'https://xpay.wsmfk.com');
        $this->config->set('pay.epay_pid', $enabled ? '1001' : '');
        $this->config->set('pay.epay_key', $enabled ? 'SECRET' : '');
        $this->config->set('pay.notify_url', 'https://clapi.example.com/api/v1/wallet/recharge/notify');
    }

    /**
     * @return array<string,string>
     */
    private function notify(RechargeOrder $order, string $money = '6.00'): array
    {
        $params = [
            'pid' => '1001',
            'trade_no' => 'T-1001',
            'out_trade_no' => (string) $order->order_no,
            'type' => 'alipay',
            'name' => '金币充值',
            'money' => $money,
            'trade_status' => 'TRADE_SUCCESS',
            'sign_type' => 'MD5',
        ];
        $params['sign'] = EpayService::sign($params, 'SECRET');

        return $params;
    }

    private function pendingOrder(User $user, int $coins, int $amountFen): RechargeOrder
    {
        /** @var RechargeOrder $order */
        $order = RechargeOrder::query()->create([
            'order_no' => 'P' . date('YmdHis') . str_pad((string) (++$this->sequence), 4, '0', STR_PAD_LEFT),
            'user_id' => $user->id,
            'coins' => $coins,
            'amount_fen' => $amountFen,
            'pay_channel' => RechargeOrder::CHANNEL_ALIPAY,
            'source' => RechargeOrder::SOURCE_ONLINE,
            'status' => RechargeOrder::STATUS_PENDING,
        ]);

        return $order;
    }

    private function male(int $coins = 0): User
    {
        return $this->makeUser(User::GENDER_MALE, $coins);
    }

    private function female(): User
    {
        return $this->makeUser(User::GENDER_FEMALE, 0);
    }

    private function makeUser(int $gender, int $coins): User
    {
        $phone = '13600000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => '测试' . $this->sequence,
            'avatar' => 'https://cdn.example.com/a.jpg',
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id, 'balance' => $coins, 'total_recharge' => 0]);

        return $user;
    }

    private function clear(): void
    {
        CoinLog::query()->delete();
        CoinWallet::query()->delete();
        RechargeOrder::query()->delete();
        User::query()->delete();
    }
}
