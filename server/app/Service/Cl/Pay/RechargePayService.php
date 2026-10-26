<?php

declare(strict_types=1);

namespace App\Service\Cl\Pay;

use App\Exception\ClBusinessException;
use App\Model\Cl\RechargeOrder;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Service\Cl\Support\OrderNo;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\RechargePackCatalog;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * App 在线充值的业务层（万世码支付 / 支付宝）。
 *
 * 流程：
 * 1. App 只传档位下标 → 服务端按 `recharge.packs` 定价，落一张待支付单；
 * 2. 调 `EpayService` 取支付宝付款链接，交给 App 自己画二维码；
 * 3. 码支付异步回调 → 验签 + 核对金额 → 事务内改成已支付并加金币；
 * 4. 回调丢了可以在后台「查单补发」，走同一段入账逻辑。
 *
 * 钱的规则不变（docs/06）：加金币只走 `CoinWalletService`，
 * 幂等靠钱包流水的 `(type, ref_type, ref_id)`，同一张充值单只入账一次。
 */
final class RechargePayService
{
    private const REF_RECHARGE = 'recharge_order';

    private const TITLE_ONLINE = '在线充值 · 支付宝';

    public function __construct(
        private readonly EpayService $epayService,
        private readonly RechargePackCatalog $packCatalog,
        private readonly CoinWalletService $coinWalletService,
        private readonly LoggerInterface $logger,
    ) {}

    public function enabled(): bool
    {
        return $this->epayService->enabled();
    }

    /**
     * 下在线支付单并取码。
     *
     * @return array{
     *     order_no: string, coins: int, amount_fen: int, amount_text: string,
     *     pay_url: string, status: int, expire_at: string
     * }
     */
    public function create(User $user, int $packIndex, string $clientIp): array
    {
        $this->assertMale($user);
        if (! $this->epayService->enabled()) {
            throw new ClBusinessException(BizCode::FEATURE_NOT_OPEN, '在线支付暂未开放，请联系客服充值');
        }

        $pack = $this->packCatalog->find($packIndex);
        if ($pack === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '充值档位不存在，请刷新后重试');
        }

        $amountFen = $pack['yuan'] * 100;
        $orderNo = OrderNo::recharge('P');

        /** @var RechargeOrder $order */
        $order = RechargeOrder::query()->create([
            'order_no' => $orderNo,
            'user_id' => $user->id,
            'coins' => $pack['coins'],
            'amount_fen' => $amountFen,
            'pay_channel' => RechargeOrder::CHANNEL_ALIPAY,
            'source' => RechargeOrder::SOURCE_ONLINE,
            'status' => RechargeOrder::STATUS_PENDING,
            'remark' => 'App 在线充值',
        ]);

        try {
            $payment = $this->epayService->createPayment(
                $orderNo,
                $amountFen,
                sprintf('金币充值 %d 金币', $pack['coins']),
                $clientIp,
            );
        } catch (Throwable $e) {
            // 取码失败的单直接关掉，别留一堆「待支付」垃圾单
            $order->status = RechargeOrder::STATUS_CLOSED;
            $order->remark = '取码失败：' . mb_substr($e->getMessage(), 0, 100);
            $order->save();

            throw $e;
        }

        $order->trade_no = $payment['trade_no'] === '' ? null : $payment['trade_no'];
        $order->pay_url = $payment['pay_url'];
        $order->save();

        return $this->payload($order);
    }

    /**
     * App 轮询支付结果。只查本地订单（回调负责改状态），不每次去敲码支付。
     *
     * @return array{order_no: string, coins: int, amount_fen: int, amount_text: string, status: int, balance: int}
     */
    public function status(User $user, string $orderNo): array
    {
        $this->assertMale($user);

        /** @var null|RechargeOrder $order */
        $order = RechargeOrder::query()
            ->where('order_no', $orderNo)
            ->where('user_id', $user->id)
            ->first();
        if ($order === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '充值订单不存在');
        }

        if ((int) $order->status === RechargeOrder::STATUS_PENDING && $this->expired($order)) {
            $order->status = RechargeOrder::STATUS_CLOSED;
            $order->save();
        }

        return [
            'order_no' => (string) $order->order_no,
            'coins' => (int) $order->coins,
            'amount_fen' => (int) $order->amount_fen,
            'amount_text' => EpayService::fenToYuan((int) $order->amount_fen),
            'status' => (int) $order->status,
            'balance' => $this->coinWalletService->balanceOf($user->id),
        ];
    }

    /**
     * 码支付异步回调。返回 true 时控制器回 `success`，让平台停止重试。
     */
    public function handleNotify(array $params): bool
    {
        if (! $this->epayService->verifyNotify($params)) {
            $this->logger->warning('码支付回调验签失败', [
                'out_trade_no' => (string) ($params['out_trade_no'] ?? ''),
            ]);

            return false;
        }

        // 非成功状态也回 success：平台只要收到 success 就不会再重试，这里没什么可做的
        if ((string) ($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return true;
        }

        $orderNo = (string) ($params['out_trade_no'] ?? '');
        /** @var null|RechargeOrder $order */
        $order = RechargeOrder::query()->where('order_no', $orderNo)->first();
        if ($order === null) {
            $this->logger->warning('码支付回调找不到订单', ['out_trade_no' => $orderNo]);

            return false;
        }

        // 金额必须和下单时一致，不然是伪造或串单
        $paidFen = EpayService::yuanToFen((string) ($params['money'] ?? ''));
        if ($paidFen === null || $paidFen !== (int) $order->amount_fen) {
            $this->logger->warning('码支付回调金额不符', [
                'out_trade_no' => $orderNo,
                'expected_fen' => (int) $order->amount_fen,
                'paid' => (string) ($params['money'] ?? ''),
            ]);

            return false;
        }

        $this->markPaid($order, (string) ($params['trade_no'] ?? ''), '码支付回调');

        return true;
    }

    /**
     * 后台「查单补发」：回调丢了时主动查一次。
     *
     * @return array{paid: bool, status: int, balance: int}
     */
    public function sync(int $orderId): array
    {
        /** @var null|RechargeOrder $order */
        $order = RechargeOrder::query()->find($orderId);
        if ($order === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '充值订单不存在');
        }
        if ((string) $order->source !== RechargeOrder::SOURCE_ONLINE) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '只有在线支付单可以查单');
        }

        if ((int) $order->status !== RechargeOrder::STATUS_PAID) {
            if ($this->epayService->isPaidByQuery((string) $order->order_no)) {
                $this->markPaid($order, '', '后台查单补发');
            }
        }

        $fresh = RechargeOrder::query()->find($orderId);

        return [
            'paid' => $fresh !== null && (int) $fresh->status === RechargeOrder::STATUS_PAID,
            'status' => $fresh === null ? (int) $order->status : (int) $fresh->status,
            'balance' => $this->coinWalletService->balanceOf((int) $order->user_id),
        ];
    }

    /**
     * 把订单改成已支付并加金币。并发/重复回调都安全：
     * 锁订单行 + 钱包流水幂等，同一张单只会加一次。
     */
    private function markPaid(RechargeOrder $order, string $tradeNo, string $remark): void
    {
        Db::transaction(function () use ($order, $tradeNo, $remark): void {
            /** @var null|RechargeOrder $locked */
            $locked = RechargeOrder::query()->lockForUpdate()->find($order->id);
            if ($locked === null || (int) $locked->status === RechargeOrder::STATUS_PAID) {
                return;
            }

            $locked->status = RechargeOrder::STATUS_PAID;
            $locked->paid_at = Carbon::now();
            if ($tradeNo !== '') {
                $locked->trade_no = $tradeNo;
            }
            if ($remark !== '') {
                $locked->remark = mb_substr($remark, 0, 100);
            }
            $locked->save();

            $this->coinWalletService->increase(
                (int) $locked->user_id,
                (int) $locked->coins,
                CoinLogType::RECHARGE,
                self::REF_RECHARGE,
                (int) $locked->id,
                self::TITLE_ONLINE,
                $tradeNo === '' ? null : '码支付单号 ' . $tradeNo,
            );
        });
    }

    private function expired(RechargeOrder $order): bool
    {
        $createdAt = $order->created_at;
        if ($createdAt === null) {
            return false;
        }

        return $createdAt->copy()->addMinutes($this->epayService->expireMinutes())->isPast();
    }

    /**
     * @return array{
     *     order_no: string, coins: int, amount_fen: int, amount_text: string,
     *     pay_url: string, status: int, expire_at: string
     * }
     */
    private function payload(RechargeOrder $order): array
    {
        $createdAt = $order->created_at ?? Carbon::now();

        return [
            'order_no' => (string) $order->order_no,
            'coins' => (int) $order->coins,
            'amount_fen' => (int) $order->amount_fen,
            'amount_text' => EpayService::fenToYuan((int) $order->amount_fen),
            'pay_url' => (string) $order->pay_url,
            'status' => (int) $order->status,
            'expire_at' => $createdAt->copy()->addMinutes($this->epayService->expireMinutes())->toDateTimeString(),
        ];
    }

    private function assertMale(User $user): void
    {
        if (! $user->isMale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '金币钱包只有男用户有');
        }
    }
}
