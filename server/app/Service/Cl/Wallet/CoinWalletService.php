<?php

declare(strict_types=1);

namespace App\Service\Cl\Wallet;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinWallet;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Repository\Cl\CoinLogRepository;
use App\Repository\Cl\CoinWalletRepository;
use Hyperf\DbConnection\Db;

/**
 * 全项目唯一改金币余额的入口（docs/02 第 2.4 节、docs/04 第 4 节）。
 *
 * 规则（docs/06 第 1 节）：
 * - 金币是整数，余额不允许为负；
 * - 余额变动和写流水必须在同一个事务里；
 * - 事务里先 lockForUpdate 锁住钱包行，避免并发扣减出现负数；
 * - 同一个 (type, ref_type, ref_id) 只记一次账（幂等）。
 */
class CoinWalletService
{
    public function __construct(
        protected readonly CoinWalletRepository $repository,
        protected readonly CoinLogRepository $logRepository,
    ) {}

    public function createForUser(int $userId): void
    {
        $this->repository->createForUser($userId);
    }

    /**
     * 加金币。
     */
    public function increase(
        int $userId,
        int $amount,
        CoinLogType $type,
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
            if ($type->isRecharge()) {
                $wallet->total_recharge += $amount;
            }
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => $type->value,
                'change' => $amount,
                'balance_after' => $wallet->balance,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
                'operator_id' => $operatorId,
            ]);
        });
    }

    /**
     * 扣金币。余额不足抛 20001（data 带 balance、needed），整个事务回滚。
     */
    public function decrease(
        int $userId,
        int $amount,
        CoinLogType $type,
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
            if ($wallet->balance < $amount) {
                throw new ClBusinessException(BizCode::COIN_NOT_ENOUGH, null, [
                    'balance' => $wallet->balance,
                    'needed' => $amount,
                ]);
            }
            $wallet->balance -= $amount;
            if ($type->isSpending()) {
                $wallet->total_spent += $amount;
            }
            $wallet->save();

            $this->logRepository->add([
                'user_id' => $userId,
                'type' => $type->value,
                'change' => -$amount,
                'balance_after' => $wallet->balance,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'title' => $title,
                'remark' => $remark,
                'operator_id' => $operatorId,
            ]);
        });
    }

    public function balanceOf(int $userId): int
    {
        $wallet = $this->repository->findByUserId($userId);

        return $wallet === null ? 0 : (int) $wallet->balance;
    }

    /**
     * 批量查余额（后台的用户列表要一次把整页人都带出来）。
     *
     * 读也走这个服务、而不是让别处直接查钱包表：钱相关的读写**只在这一个地方**，
     * `scripts/check-money.sh` 就是靠这条规矩挡住「绕过钱包服务」的改动。
     *
     * @param array<int,int> $userIds
     * @return array<int,array{balance: int, totalRecharge: int}> userId => 余额
     */
    public function summaryOfMany(array $userIds): array
    {
        $map = [];
        foreach ($this->repository->listByUserIds($userIds) as $userId => $wallet) {
            $map[$userId] = [
                'balance' => (int) $wallet->balance,
                'totalRecharge' => (int) $wallet->total_recharge,
            ];
        }

        return $map;
    }

    private function lockWallet(int $userId): CoinWallet
    {
        $wallet = $this->repository->lockByUserId($userId);
        if (! $wallet instanceof CoinWallet) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '金币钱包不存在');
        }
        return $wallet;
    }

    private function assertAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('金额必须是正整数');
        }
    }
}
