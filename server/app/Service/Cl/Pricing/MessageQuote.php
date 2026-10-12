<?php

declare(strict_types=1);

namespace App\Service\Cl\Pricing;

/**
 * 一次私信报价的结果（docs/06 第 3.2 节）。
 *
 * [price] = 0 表示这条免费；收费时 [price] 是**发送那一刻**女方等级对应的单价。
 */
final class MessageQuote
{
    public const FREE_MUTUAL_FOLLOW = 'MUTUAL_FOLLOW';

    public const FREE_PAIR = 'PAIR_FREE';

    public const FREE_VIP = 'VIP_FREE';

    public function __construct(
        /** 金币；0 = 免费 */
        public readonly int $price,
        /** 免费原因，收费时为 null */
        public readonly ?string $freeReason,
        /** 这对男女还剩几条免费（不含会员额度）；收费时为 0 */
        public readonly int $pairFreeRemaining,
        /** 会员今天还剩几条免费（不是会员或已用完为 0） */
        public readonly int $vipFreeRemaining,
        /** 女方等级（要写进消息记录） */
        public readonly int $receiverLevel,
    ) {}

    public function isFree(): bool
    {
        return $this->freeReason !== null;
    }
}
