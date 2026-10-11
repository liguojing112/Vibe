<?php

declare(strict_types=1);

namespace App\Service\Cl\Wallet;

use App\Exception\ClBusinessException;
use App\Model\Cl\PointWallet;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\PointLogType;
use App\Repository\Cl\PointLogRepository;
use App\Repository\Cl\PointWalletRepository;
use Hyperf\DbConnection\Db;

/**
 * 全项目唯一改积分余额的入口（docs/02 第 2.4 节、docs/04 第 4 节）。
 *
 * change / frozen_change 的对应关系严格按 docs/04 第 4 节的表：
 * MESSAGE_INCOME / GIFT_INCOME / TASK  → change +N，frozen 不变
 * WITHDRAW_FREEZE                      → change −N，frozen +N
 * WITHDRAW_REFUND                      → change +N，frozen −N
 * WITHDRAW_PAID                        → change 0，frozen −N
 * ADMIN_ADJUST                        → change ±N，frozen 不变
 */
class PointWalletService
{
    public function __construct(
        protected readonly PointWalletRepository $repository,
        protected readonly PointLogRepository $logRepository,
    ) {}

    public function createForUser(int $userId): void
    {
        $this->repository->createForUser($userId);
    }

    /**
     * 加积分（私信收益、礼物收益、任务奖励、后台补发）。
     */
    public function increase(
        int $userId,
        int $amount,
        PointLogType $type,
        string $refType,
        int $refId,
        string $title,
        ?string $remark = null,
        ?int $operatorId = null
    ): void {
        $this->assertAmount($amount);

        Db::transaction(function () use ($userId, $amount, $type, $refType, $refId, $title, $remark, $operatorId): void {
            if ($this->logRepository->existsByRef($type->value, $refType, $refId)) {
                return;
            }
            $wallet = $this->lockWallet($userId);
            $wallet->balance += $amount;
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => $type->value,
                'change' => $amount,
                'frozen_change' => 0,
                'balance_after' => $wallet->balance,
                'frozen_after' => $wallet->frozen,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
                'operator_id' => $operatorId,
            ]);
        });
    }

    /**
     * 扣积分。不足抛 20002。
     */
    public function decrease(
        int $userId,
        int $amount,
        PointLogType $type,
        string $refType,
        int $refId,
        string $title,
        ?string $remark = null,
        ?int $operatorId = null
    ): void {
        $this->assertAmount($amount);

        Db::transaction(function () use ($userId, $amount, $type, $refType, $refId, $title, $remark, $operatorId): void {
            if ($this->logRepository->existsByRef($type->value, $refType, $refId)) {
                return;
            }
            $wallet = $this->lockWallet($userId);
            $this->assertBalanceEnough($wallet, $amount);
            $wallet->balance -= $amount;
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => $type->value,
                'change' => -$amount,
                'frozen_change' => 0,
                'balance_after' => $wallet->balance,
                'frozen_after' => $wallet->frozen,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
                'operator_id' => $operatorId,
            ]);
        });
    }

    /**
     * 提现申请：可用 −N，冻结 +N（docs/06 第 9.1 节）。
     */
    public function freeze(int $userId, int $amount, string $refType, int $refId, string $title, ?string $remark = null): void
    {
        $this->assertAmount($amount);

        Db::transaction(function () use ($userId, $amount, $refType, $refId, $title, $remark): void {
            if ($this->logRepository->existsByRef(PointLogType::WITHDRAW_FREEZE->value, $refType, $refId)) {
                return;
            }
            $wallet = $this->lockWallet($userId);
            $this->assertBalanceEnough($wallet, $amount);
            $wallet->balance -= $amount;
            $wallet->frozen += $amount;
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => PointLogType::WITHDRAW_FREEZE->value,
                'change' => -$amount,
                'frozen_change' => $amount,
                'balance_after' => $wallet->balance,
                'frozen_after' => $wallet->frozen,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
            ]);
        });
    }

    /**
     * 提现驳回：冻结 −N，可用 +N（原路退回）。
     */
    public function unfreeze(int $userId, int $amount, string $refType, int $refId, string $title, ?string $remark = null): void
    {
        $this->assertAmount($amount);

        Db::transaction(function () use ($userId, $amount, $refType, $refId, $title, $remark): void {
            if ($this->logRepository->existsByRef(PointLogType::WITHDRAW_REFUND->value, $refType, $refId)) {
                return;
            }
            $wallet = $this->lockWallet($userId);
            if ($wallet->frozen < $amount) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '冻结积分不足，无法退回');
            }
            $wallet->frozen -= $amount;
            $wallet->balance += $amount;
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => PointLogType::WITHDRAW_REFUND->value,
                'change' => $amount,
                'frozen_change' => -$amount,
                'balance_after' => $wallet->balance,
                'frozen_after' => $wallet->frozen,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
            ]);
        });
    }

    /**
     * 标记已打款：冻结 −N（真正扣掉），可用不变，累计提现 +N。
     */
    public function settleFrozen(int $userId, int $amount, string $refType, int $refId, string $title, ?string $remark = null): void
    {
        $this->assertAmount($amount);

        Db::transaction(function () use ($userId, $amount, $refType, $refId, $title, $remark): void {
            if ($this->logRepository->existsByRef(PointLogType::WITHDRAW_PAID->value, $refType, $refId)) {
                return;
            }
            $wallet = $this->lockWallet($userId);
            if ($wallet->frozen < $amount) {
                throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '冻结积分不足，无法结算');
            }
            $wallet->frozen -= $amount;
            $wallet->total_withdrawn += $amount;
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => PointLogType::WITHDRAW_PAID->value,
                'change' => 0,
                'frozen_change' => -$amount,
                'balance_after' => $wallet->balance,
                'frozen_after' => $wallet->frozen,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
            ]);
        });
    }

    /**
     * @return array{balance: int, frozen: int}
     */
    public function summaryOf(int $userId): array
    {
        $wallet = $this->repository->findByUserId($userId);
        if ($wallet === null) {
            return ['balance' => 0, 'frozen' => 0];
        }

        return [
            'balance' => (int) $wallet->balance,
            'frozen' => (int) $wallet->frozen,
        ];
    }

    private function lockWallet(int $userId): PointWallet
    {
        $wallet = $this->repository->lockByUserId($userId);
        if (! $wallet instanceof PointWallet) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '积分钱包不存在');
        }
        return $wallet;
    }

    private function assertBalanceEnough(PointWallet $wallet, int $amount): void
    {
        if ($wallet->balance < $amount) {
            throw new ClBusinessException(BizCode::POINT_NOT_ENOUGH, null, [
                'balance' => $wallet->balance,
                'needed' => $amount,
            ]);
        }
    }

    private function assertAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须是正整数');
        }
    }
}
