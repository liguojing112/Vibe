<?php

declare(strict_types=1);

namespace App\Service\Cl\Withdraw;

use App\Exception\ClBusinessException;
use App\Model\Cl\Notice;
use App\Model\Cl\User;
use App\Model\Cl\WithdrawAccount;
use App\Model\Cl\WithdrawOrder;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\RealNameRepository;
use App\Repository\Cl\WithdrawAccountRepository;
use App\Repository\Cl\WithdrawOrderRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Notice\NoticeService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;

/**
 * 提现（docs/06 第 9 节，P28）。
 *
 * 三条纪律：
 * 1. **积分一动就走 `PointWalletService`**（冻结 / 原路退回 / 结算），这里只负责流程和单据；
 * 2. **状态只能按表流转**（待审核 → 通过 → 已打款，或 待审核/通过 → 驳回），别的组合一律拒；
 * 3. **金额一律用「分」算**：手续费是「金额分 × 万分比 ÷ 10000 向下取整」，
 *    用分算才不会出现浮点误差（铁律 6）。
 */
final class WithdrawService
{
    /** 积分流水的 `ref_type`：`ref_id` 指向 `cl_withdraw_order.id`。 */
    public const REF_TYPE = 'withdraw_order';

    /** P28 每页条数（docs/05 第 1 节分页约定 20）。 */
    public const PAGE_SIZE = 20;

    private const ACCOUNT_TYPES = [
        WithdrawAccount::TYPE_ALIPAY,
        WithdrawAccount::TYPE_WECHAT,
        WithdrawAccount::TYPE_BANK,
    ];

    public function __construct(
        private readonly WithdrawOrderRepository $orderRepository,
        private readonly WithdrawAccountRepository $accountRepository,
        private readonly PointWalletService $pointWalletService,
        private readonly RealNameRepository $realNameRepository,
        private readonly NoticeService $noticeService,
        private readonly AppConfigService $configService,
    ) {}

    use RetriesTransactions;

    /**
     * `/withdraw/info`：可提现金额、档位（每个是否可选）、规则、实名状态、收款信息、今日剩余次数。
     *
     * @return array<string,mixed>
     */
    public function info(User $user): array
    {
        $this->assertFemale($user);

        $wallet = $this->pointWalletService->summaryOf($user->id);
        $balance = (int) $wallet['balance'];
        $pointPerYuan = $this->pointPerYuan();
        $feeRate = $this->feeRate();
        $account = $this->accountRepository->findByUserId($user->id);
        $limit = $this->dailyLimit();
        $todayCount = $this->todayCount($user);
        $realName = $this->realNameRepository->findByUserId($user->id);

        return [
            // 可提现金额（元）给整数：档位都是整数元，页面按它把不够的档位置灰
            'amount_yuan' => $pointPerYuan > 0 ? intdiv($balance, $pointPerYuan) : 0,
            'points' => $balance,
            'frozen_points' => (int) $wallet['frozen'],
            'tiers' => $this->tiersPayload($balance),
            'fee_rate' => $feeRate,
            'notice' => (string) $this->configService->get('withdraw.notice', ''),
            'real_name_status' => (int) $user->real_name_status,
            'real_name_passed' => $user->real_name_status === User::REAL_NAME_PASSED,
            'real_name' => $realName === null ? '' : (string) $realName->real_name,
            'need_real_name' => (bool) $this->configService->get('withdraw.need_real_name', true),
            'account' => $account === null ? null : [
                'account_type' => (string) $account->account_type,
                'account_no' => (string) $account->account_no,
                'bank_name' => $account->bank_name,
            ],
            'daily_limit' => $limit,
            'today_count' => $todayCount,
            // 0 = 不限次；那时没有「还剩几次」这回事
            'remaining_today' => $limit > 0 ? max(0, $limit - $todayCount) : null,
        ];
    }

    /**
     * `/withdraw/apply`：申请提现（docs/06 第 9.1 节的八步）。
     *
     * @return array<string,mixed>
     */
    public function apply(
        User $user,
        int $amountYuan,
        string $accountType,
        string $accountNo,
        ?string $bankName = null,
    ): array {
        $this->assertFemale($user);

        // ① 实名（开关打开时必须有）
        if ((bool) $this->configService->get('withdraw.need_real_name', true)
            && $user->real_name_status !== User::REAL_NAME_PASSED
        ) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '请先完成实名认证');
        }

        // ② 档位
        if (! \in_array($amountYuan, $this->tiers(), true) || $amountYuan < $this->minYuan()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '请选择提现档位里的金额');
        }

        $accountType = strtoupper(trim($accountType));
        if (! \in_array($accountType, self::ACCOUNT_TYPES, true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '收款方式不正确');
        }
        $accountNo = trim($accountNo);
        if ($accountNo === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '请填写收款账号');
        }
        $bankName = $bankName === null ? null : trim($bankName);
        if ($accountType === WithdrawAccount::TYPE_BANK && ($bankName ?? '') === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '请填写开户行');
        }

        // ③ 次数（驳回的也算）
        $limit = $this->dailyLimit();
        if ($limit > 0 && $this->todayCount($user) >= $limit) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '今天已经申请过了，请明天再来');
        }

        // ④⑤ 需要的积分和余额判断：`freeze()` 自己会锁钱包 + 余额不足抛 20002
        $pointPerYuan = $this->pointPerYuan();
        if ($pointPerYuan <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '提现功能未配置积分汇率');
        }
        $points = $amountYuan * $pointPerYuan;

        // ⑥ 手续费（分）：金额分 × 万分比 ÷ 10000，向下取整
        $amountFen = $amountYuan * 100;
        $feeFen = intdiv($amountFen * $this->feeRate(), 10000);
        $actualFen = $amountFen - $feeFen;

        $realName = $this->realNameRepository->findByUserId($user->id);
        $accountName = $realName === null ? '' : (string) $realName->real_name;
        $orderNo = $this->newOrderNo();

        $order = $this->runTransaction(function () use (
            $user, $amountFen, $points, $feeFen, $actualFen, $accountType, $accountNo, $accountName, $bankName, $orderNo
        ): WithdrawOrder {
            // ⑦⑧ 单据先落库再冻结：冻结流水的 `ref_id` 要指向单据 id
            /** @var WithdrawOrder $order */
            $order = $this->orderRepository->create([
                'order_no' => $orderNo,
                'user_id' => $user->id,
                'amount_fen' => $amountFen,
                'points' => $points,
                'fee_fen' => $feeFen,
                'actual_fen' => $actualFen,
                'account_type' => $accountType,
                'account_no' => $accountNo,
                // 收款人一律是实名姓名，免得把钱提到别人账上
                'account_name' => $accountName,
                'bank_name' => $bankName,
                'status' => WithdrawOrder::STATUS_PENDING,
            ]);

            $this->pointWalletService->freeze(
                $user->id,
                $points,
                self::REF_TYPE,
                (int) $order->id,
                sprintf('提现冻结 · %d 元', intdiv($amountFen, 100)),
            );

            $this->saveAccount($user->id, $accountType, $accountNo, $bankName);

            return $order;
        });

        $wallet = $this->pointWalletService->summaryOf($user->id);

        return [
            'order_no' => (string) $order->order_no,
            'amount_fen' => (int) $order->amount_fen,
            'points' => (int) $order->points,
            'fee_fen' => (int) $order->fee_fen,
            'actual_fen' => (int) $order->actual_fen,
            'status' => (int) $order->status,
            'status_text' => WithdrawOrder::STATUS_TEXTS[(int) $order->status],
            'balance' => (int) $wallet['balance'],
            'frozen' => (int) $wallet['frozen'],
        ];
    }

    /**
     * `/withdraw/records`：提现记录（P28）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int, page: int}
     */
    public function records(User $user, int $page): array
    {
        $this->assertFemale($user);

        return [
            'list' => $this->orderRepository->pageForUser(
                $user->id,
                max(0, ($page - 1) * self::PAGE_SIZE),
                self::PAGE_SIZE,
            ),
            'total' => $this->orderRepository->countForUser($user->id),
            'page' => $page,
        ];
    }

    /**
     * 后台审核通过（`docs/08` T7 接 HTTP）：积分**继续冻结**，等打款。
     */
    public function approve(int $orderId, int $reviewerId): void
    {
        $this->runTransaction(function () use ($orderId, $reviewerId): void {
            $order = $this->requireOrder($orderId, [WithdrawOrder::STATUS_PENDING], '这张提现单不是待审核状态');

            $this->orderRepository->saveById($order->id, [
                'status' => WithdrawOrder::STATUS_APPROVED,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => Carbon::now()->toDateTimeString(),
            ]);

            $this->noticeService->send(
                (int) $order->user_id,
                Notice::CATEGORY_SYSTEM,
                '提现审核通过',
                sprintf('你的 %s 元提现申请已通过审核，正在安排打款', $this->yuanText((int) $order->amount_fen)),
                'WITHDRAW',
                (int) $order->id,
            );
        });
    }

    /**
     * 后台驳回（必须填原因）：**积分原路退回**（冻结 −，可用 +）。
     *
     * 待审核和已通过都能驳回（还没打款就来得及）。
     */
    public function reject(int $orderId, int $reviewerId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '驳回必须填写原因');
        }

        $this->runTransaction(function () use ($orderId, $reviewerId, $reason): void {
            $order = $this->requireOrder(
                $orderId,
                [WithdrawOrder::STATUS_PENDING, WithdrawOrder::STATUS_APPROVED],
                '这张提现单已经打款或已驳回，不能再驳回',
            );

            $this->orderRepository->saveById($order->id, [
                'status' => WithdrawOrder::STATUS_REJECTED,
                'reject_reason' => $reason,
                'reviewer_id' => $reviewerId,
                'reviewed_at' => Carbon::now()->toDateTimeString(),
            ]);

            $this->pointWalletService->unfreeze(
                (int) $order->user_id,
                (int) $order->points,
                self::REF_TYPE,
                (int) $order->id,
                '提现驳回退回 · ' . $this->yuanText((int) $order->amount_fen) . ' 元',
            );

            $this->noticeService->send(
                (int) $order->user_id,
                Notice::CATEGORY_SYSTEM,
                '提现被驳回',
                sprintf('%s 元提现被驳回：%s。积分已退回你的账户', $this->yuanText((int) $order->amount_fen), $reason),
                'WITHDRAW',
                (int) $order->id,
            );
        });
    }

    /**
     * 后台标记已打款（必须填流水号或备注）：**冻结真正扣掉**，累计提现增加。
     */
    public function markPaid(int $orderId, int $payerId, string $payProof): void
    {
        $payProof = trim($payProof);
        if ($payProof === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '请填写打款流水号或备注');
        }

        $this->runTransaction(function () use ($orderId, $payerId, $payProof): void {
            $order = $this->requireOrder($orderId, [WithdrawOrder::STATUS_APPROVED], '这张提现单还没通过审核，不能打款');

            $this->orderRepository->saveById($order->id, [
                'status' => WithdrawOrder::STATUS_PAID,
                'pay_proof' => $payProof,
                'payer_id' => $payerId,
                'paid_at' => Carbon::now()->toDateTimeString(),
            ]);

            $this->pointWalletService->settleFrozen(
                (int) $order->user_id,
                (int) $order->points,
                self::REF_TYPE,
                (int) $order->id,
                '提现成功 · ' . $this->yuanText((int) $order->amount_fen) . ' 元',
            );

            $this->noticeService->send(
                (int) $order->user_id,
                Notice::CATEGORY_SYSTEM,
                '提现已打款',
                sprintf('实到 %s 元已打款到你的账户，请注意查收', $this->yuanText((int) $order->actual_fen)),
                'WITHDRAW',
                (int) $order->id,
            );
        });
    }

    /**
     * 事务里锁住单据行再校验状态 —— 并发审同一张单时，第二个协程会等第一个提交，
     * 拿到的就是新状态，不会出现「两个人同时点通过」都成功。
     *
     * @param array<int,int> $allowed
     */
    private function requireOrder(int $orderId, array $allowed, string $message): WithdrawOrder
    {
        $order = $this->orderRepository->lockById($orderId);
        if ($order === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '提现单不存在');
        }
        if (! \in_array((int) $order->status, $allowed, true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, $message);
        }

        return $order;
    }

    private function saveAccount(int $userId, string $accountType, string $accountNo, ?string $bankName): void
    {
        $existing = $this->accountRepository->findByUserId($userId);
        $payload = [
            'user_id' => $userId,
            'account_type' => $accountType,
            'account_no' => $accountNo,
            'bank_name' => $bankName,
        ];

        if ($existing === null) {
            $this->accountRepository->create($payload);

            return;
        }

        $this->accountRepository->saveById($existing->id, $payload);
    }

    /**
     * 档位列表（P28 的「档位是否可选」）：积分不够的档位置灰。
     *
     * @return array<int,array<string,mixed>>
     */
    private function tiersPayload(int $balance): array
    {
        $pointPerYuan = $this->pointPerYuan();
        $feeRate = $this->feeRate();
        $tiers = [];

        foreach ($this->tiers() as $yuan) {
            $amountFen = $yuan * 100;
            $feeFen = intdiv($amountFen * $feeRate, 10000);
            $points = $pointPerYuan > 0 ? $yuan * $pointPerYuan : 0;

            $tiers[] = [
                'yuan' => $yuan,
                'points' => $points,
                'fee_fen' => $feeFen,
                'actual_fen' => $amountFen - $feeFen,
                'enabled' => $points > 0 && $balance >= $points,
            ];
        }

        return $tiers;
    }

    /**
     * 「50」这种整元的文字（金额全是分，展示时去掉小数点后两位）。
     *
     * 页面上的「¥12.50」由客户端拿 `MoneyFormat.rmb(分)` 拼，这里只用于服务端拼通知文案。
     */
    private function yuanText(int $fen): string
    {
        $yuan = intdiv($fen, 100);
        $cents = $fen % 100;

        return $cents === 0 ? (string) $yuan : sprintf('%d.%02d', $yuan, $cents);
    }

    /** 提现单号：日期 + 5 字节随机（唯一索引兜底，撞了就撞了，概率可忽略）。 */
    private function newOrderNo(): string
    {
        return 'W' . Carbon::now()->format('YmdHis') . strtoupper(bin2hex(random_bytes(5)));
    }

    /**
     * @return array<int,int> 档位（元），升序去重
     */
    private function tiers(): array
    {
        $raw = $this->configService->get('withdraw.tiers', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! \is_array($raw)) {
            return [];
        }

        $tiers = [];
        foreach ($raw as $yuan) {
            if (is_numeric($yuan) && (int) $yuan > 0) {
                $tiers[] = (int) $yuan;
            }
        }

        $tiers = array_values(array_unique($tiers));
        sort($tiers);

        return $tiers;
    }

    private function minYuan(): int
    {
        return max(0, (int) $this->configService->get('withdraw.min_yuan', 0));
    }

    private function dailyLimit(): int
    {
        return max(0, (int) $this->configService->get('withdraw.daily_limit', 0));
    }

    private function feeRate(): int
    {
        return max(0, (int) $this->configService->get('withdraw.fee_rate', 0));
    }

    private function pointPerYuan(): int
    {
        return max(0, (int) $this->configService->get('point.per_yuan', 0));
    }

    /** 今天已经申请了几次（驳回的也算）。 */
    private function todayCount(User $user): int
    {
        $start = Carbon::now()->startOfDay();

        return $this->orderRepository->countToday(
            $user->id,
            $start->toDateTimeString(),
            $start->copy()->addDay()->toDateTimeString(),
        );
    }

    /** 提现是女用户的功能（`docs/06` 第 9.1 节第一行就写着「女用户」）。 */
    private function assertFemale(User $user): void
    {
        if (! $user->isFemale()) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '提现只有女用户有');
        }
    }
}
