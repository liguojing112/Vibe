<?php

declare(strict_types=1);

namespace App\Service\Cl\Wallet;

use App\Repository\Cl\PointWalletRepository;

/**
 * 全项目唯一改积分余额的入口（docs/02 第 2.4 节）。
 *
 * T1.3 先只做「开户」（注册时建钱包）；increase / decrease / freeze / 流水在 T1.6 补齐。
 * 其他任何地方都不准直接动 cl_point_wallet（scripts/check-money.sh 会扫描）。
 */
class PointWalletService
{
    public function __construct(
        protected readonly PointWalletRepository $repository,
    ) {}

    public function createForUser(int $userId): void
    {
        $this->repository->createForUser($userId);
    }
}
