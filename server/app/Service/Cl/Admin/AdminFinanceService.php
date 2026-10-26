<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\RechargeOrder;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PointLogType;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminFinanceRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Pay\RechargePayService;
use App\Service\Cl\Support\OrderNo;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PlatformIncomeService;
use App\Service\Cl\Wallet\PointWalletService;

/**
 * 后台资金管理（docs/07 第 3.10~3.14 节、docs/06 第 8 节）。
 *
 * 三件必须守住的事：
 * 1. **钱只走钱包服务**：这里的每一笔加钱减钱都调 `CoinWalletService` / `PointWalletService`，
 *    自己动余额的话 `scripts/check-money.sh` 会拦，而且账就乱了；
 * 2. **每一笔都留 ref**：钱包服务靠 `(type, ref_type, ref_id)` 做幂等，
 *    ref 不唯一的话第二笔会被当成重复给吞掉 —— 所以充值用充值单 id，
 *    扣金币/调积分用 `cl_admin_action_log` 的行 id（那边本来就要记一笔谁在什么时候改的）；
 * 3. **充值和扣减的口径不一样**：充值只能给男用户（金币是消费侧的钱），
 *    扣减/调整是超管的兜底手段（退款、误操作回滚），男女都能用。
 */
final class AdminFinanceService
{
    use RetriesTransactions;

    /** 人工调整的 ref 类型（钱包流水里靠它认出来源）。 */
    private const REF_ADMIN_ACTION = 'admin_action';

    private const REF_RECHARGE = 'recharge_order';

    public function __construct(
        private readonly AdminFinanceRepository $repository,
        private readonly UserRepository $userRepository,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
        private readonly RechargePayService $rechargePayService,
    ) {}

    // ---------- 后台充值（P3.10 / P3.11）----------

    /**
     * 客服确认收到钱之后在后台录一笔充值。
     *
     * `amountFen` 可以是 0（赠送），但金币数必须为正。
     *
     * @return array{order_no: string, coins: int, balance: int}
     */
    public function recharge(
        AdminUser $operator,
        int $userId,
        int $coins,
        int $amountFen,
        string $channel,
        string $remark,
    ): array {
        if ($coins <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '充值金币数必须是正整数');
        }
        if ($amountFen < 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '实收金额不能是负数');
        }
        $channel = strtoupper(trim($channel));
        if (! in_array($channel, RechargeOrder::CHANNELS, true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '支付方式不正确');
        }

        $user = $this->userRepository->findById($userId);
        if (! $user instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }
        if ($user->gender !== User::GENDER_MALE) {
            // 金币是男的用来付私信/礼物的钱，女号充了也没处花（docs/06 第 8 节：只能选男用户）
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '只能给男用户充值金币');
        }

        /** @var RechargeOrder $order */
        $order = RechargeOrder::query()->create([
            'order_no' => OrderNo::recharge(),
            'user_id' => $user->id,
            'coins' => $coins,
            'amount_fen' => $amountFen,
            'pay_channel' => $channel,
            'remark' => $remark,
            'operator_id' => $operator->id,
        ]);

        $this->coinWalletService->increase(
            $user->id,
            $coins,
            CoinLogType::RECHARGE,
            self::REF_RECHARGE,
            (int) $order->id,
            '后台充值 · ' . $operator->username,
            $remark === '' ? null : $remark,
            $operator->id,
        );

        return [
            'order_no' => (string) $order->order_no,
            'coins' => $coins,
            'balance' => $this->coinWalletService->balanceOf($user->id),
        ];
    }

    /**
     * @return array{list: array<int,array<string,mixed>>, total: int, coins: int, amount_fen: int}
     */
    public function rechargeList(array $filters, int $page, int $pageSize): array
    {
        return $this->repository->rechargePage($filters, $page, $pageSize);
    }

    /**
     * 在线支付单查单补发：回调丢了时由后台主动去码支付查一次。
     *
     * @return array{paid: bool, status: int, balance: int}
     */
    public function syncRecharge(int $orderId): array
    {
        return $this->rechargePayService->sync($orderId);
    }

    // ---------- 流水（P3.12）----------

    public function coinLogs(array $filters, int $page, int $pageSize): array
    {
        return $this->repository->walletLogPage(false, $filters, $page, $pageSize);
    }

    public function pointLogs(array $filters, int $page, int $pageSize): array
    {
        return $this->repository->walletLogPage(true, $filters, $page, $pageSize);
    }

    // ---------- 超管的兜底手段（docs/06 第 8 节）----------

    /** 扣金币（退款、误操作回滚用）。余额不允许扣成负数。 */
    public function deductCoins(AdminUser $operator, int $userId, int $coins, string $reason): void
    {
        if ($coins <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '扣减金币数必须是正整数');
        }

        $user = $this->mustFindUser($userId);

        // 日志和钱包**必须在同一个事务里**：余额不足时 `decrease` 会抛 20001，
        // 日志要是先提交了就会留下一条「说扣了、其实没扣成」的假记录。
        $this->runTransaction(function () use ($operator, $user, $coins, $reason): void {
            $logId = $this->log($operator, $user->id, AdminActionLog::ACTION_COIN_DEDUCT, sprintf('扣减 %d 金币：%s', $coins, $reason));

            $this->coinWalletService->decrease(
                $user->id,
                $coins,
                CoinLogType::ADMIN_DEDUCT,
                self::REF_ADMIN_ACTION,
                $logId,
                '后台扣减 · ' . $operator->username,
                $reason,
                $operator->id,
            );
        });
    }

    /**
     * 调整积分：**正数是补发、负数是扣减**（docs/06 第 8 节）。
     *
     * 补发**不计入累计收益**（`total_earned_points` 只由私信/礼物分成累加，这里不碰它），
     * 所以等级不会被一次人工补发顶上去。
     */
    public function adjustPoints(AdminUser $operator, int $userId, int $points, string $reason): void
    {
        if ($points === 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '调整数量不能是 0');
        }

        $user = $this->mustFindUser($userId);

        // 同上：日志和钱包一个事务，失败时一起回滚
        $this->runTransaction(function () use ($operator, $user, $points, $reason): void {
            $logId = $this->log(
                $operator,
                $user->id,
                AdminActionLog::ACTION_POINT_ADJUST,
                sprintf('%s %d 积分：%s', $points > 0 ? '补发' : '扣减', abs($points), $reason),
            );

            $title = ($points > 0 ? '后台补发 · ' : '后台扣减 · ') . $operator->username;
            $points > 0
                ? $this->pointWalletService->increase($user->id, $points, PointLogType::ADMIN_ADJUST, self::REF_ADMIN_ACTION, $logId, $title, $reason, $operator->id)
                : $this->pointWalletService->decrease($user->id, -$points, PointLogType::ADMIN_ADJUST, self::REF_ADMIN_ACTION, $logId, $title, $reason, $operator->id);
        });
    }

    // ---------- 平台收入（P3.14）----------

    /**
     * 按天汇总。毛收入 = 金币 ÷ coin.per_yuan − 积分 ÷ point.per_yuan（docs/04 第 4 节）。
     *
     * @return array{list: array<int,array<string,mixed>>, total_coins: int, total_points: int, total_income_fen: int}
     */
    public function incomes(string $from, string $to, int $coinPerYuan, int $pointPerYuan): array
    {
        $list = $this->repository->incomeDaily($from, $to);

        $totalCoins = 0;
        $totalPoints = 0;
        $totalFen = 0;
        foreach ($list as &$row) {
            $row['income_fen'] = PlatformIncomeService::incomeFen((int) $row['coins'], (int) $row['points'], $coinPerYuan, $pointPerYuan);
            $totalCoins += (int) $row['coins'];
            $totalPoints += (int) $row['points'];
            $totalFen += (int) $row['income_fen'];
        }
        unset($row);

        return [
            'list' => $list,
            'total_coins' => $totalCoins,
            'total_points' => $totalPoints,
            'total_income_fen' => $totalFen,
        ];
    }

    // ---------- 内部 ----------

    private function mustFindUser(int $id): User
    {
        $user = $this->userRepository->findById($id);
        if (! $user instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $user;
    }

    /** 记一条后台动作流水，返回它的 id —— 钱包那边拿它当幂等 ref。 */
    private function log(AdminUser $operator, int $userId, string $action, string $detail): int
    {
        /** @var AdminActionLog $row */
        $row = AdminActionLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'detail' => mb_substr($detail, 0, 255),
            'operator_id' => $operator->id,
            'operator_name' => (string) $operator->username,
        ]);

        return (int) $row->id;
    }
}
