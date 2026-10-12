<?php

declare(strict_types=1);

namespace App\Service\Cl\Pricing;

use Hyperf\Carbon\Carbon;
use Hyperf\Redis\Redis;

/**
 * 会员每日免费私信的额度计数（docs/06 第 3.2 节第 3 步）。
 *
 * 存在 Redis，key = `vip_free:{userId}:{yyyyMMdd}`，**按 Asia/Shanghai 自然日**，
 * 所以「第二天重新计数」是天然的（换了一天就换了个 key）。
 */
final class VipFreeQuota
{
    /** 计数的存活时间：跨天就没用了，留两天足够。 */
    private const TTL_SECONDS = 2 * 86400;

    public function __construct(
        private readonly Redis $redis,
    ) {}

    public function usedToday(int $userId): int
    {
        return (int) $this->redis->get($this->key($userId));
    }

    /**
     * 用掉一条会员免费额度（发消息时调用）。
     */
    public function increment(int $userId): void
    {
        $key = $this->key($userId);
        $count = (int) $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, self::TTL_SECONDS);
        }
    }

    private function key(int $userId): string
    {
        return 'vip_free:' . $userId . ':' . Carbon::now()->format('Ymd');
    }
}
