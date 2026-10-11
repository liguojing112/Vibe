<?php

declare(strict_types=1);

namespace App\Service\Cl\Wallet;

use App\Model\Enums\Cl\PlatformIncomeSource;
use App\Repository\Cl\PlatformIncomeRepository;
use Hyperf\Carbon\Carbon;

/**
 * 平台收入明细（docs/04 第 4 节、docs/06 第 3.3/4 节）。
 *
 * 平台毛收入（元）= coins ÷ coin.per_yuan − receiver_points ÷ point.per_yuan，
 * 后台报表按 income_date 汇总（docs/04 第 4 节）。
 */
class PlatformIncomeService
{
    public function __construct(
        protected readonly PlatformIncomeRepository $repository,
    ) {}

    public function record(
        PlatformIncomeSource $source,
        int $refId,
        int $payerId,
        ?int $receiverId,
        int $coins,
        int $receiverPoints
    ): void {
        $this->repository->add([
            'source' => $source->value,
            'ref_id' => $refId,
            'payer_id' => $payerId,
            'receiver_id' => $receiverId,
            'coins' => $coins,
            'receiver_points' => $receiverPoints,
            'income_date' => Carbon::now()->toDateString(),
        ]);
    }
}
