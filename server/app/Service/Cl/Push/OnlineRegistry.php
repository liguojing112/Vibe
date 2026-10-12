<?php

declare(strict_types=1);

namespace App\Service\Cl\Push;

use Hyperf\Redis\Redis;

/**
 * 在线连接登记表（docs/02 第 2.4 节、docs/05 第 5 节）。
 *
 * 两张 Redis 表，缺一不可：
 * - `ws:fd:{fd}`   → userId：收到消息 / 断开时**由 fd 反查是谁**（fd 是 Swoole 的连接号，重启会变）
 * - `online:{userId}` → fd 集合：推送时**由人反查该推给哪些连接**
 *
 * 用集合不用单个值：同一个人可能开着多台设备，每台一条连接，都该收到推送。
 *
 * **本期按单台服务器部署**（docs/02 第 2.4 节），fd 只在同一台机器内有效，所以不需要按机器分片。
 */
final class OnlineRegistry
{
    private const FD_KEY = 'ws:fd:';

    private const ONLINE_KEY = 'online:';

    public function __construct(
        private readonly Redis $redis,
    ) {}

    /** 握手成功：把这条件连接登记到这个用户名下。 */
    public function attach(int $fd, int $userId): void
    {
        $this->redis->set(self::FD_KEY . $fd, (string) $userId);
        $this->redis->sAdd(self::ONLINE_KEY . $userId, (string) $fd);
    }

    /** 这条连接是谁的（未登记返回 null）。 */
    public function userIdOf(int $fd): ?int
    {
        $value = $this->redis->get(self::FD_KEY . $fd);
        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * 断开：清掉两张表，返回这条连接属于谁（本来就没登记则 null）。
     *
     * 集合空了要顺手删掉 key —— 否则 `isOnline()` 会一直说这个人还在线，
     * 残留的空集合也不会自己过期。
     */
    public function detach(int $fd): ?int
    {
        $userId = $this->userIdOf($fd);
        $this->redis->del(self::FD_KEY . $fd);

        if ($userId === null) {
            return null;
        }

        $onlineKey = self::ONLINE_KEY . $userId;
        $this->redis->sRem($onlineKey, (string) $fd);
        if ((int) $this->redis->sCard($onlineKey) === 0) {
            $this->redis->del($onlineKey);
        }

        return $userId;
    }

    /**
     * 这个人当前的所有连接。
     *
     * @return array<int,int>
     */
    public function fdsOf(int $userId): array
    {
        $fds = $this->redis->sMembers(self::ONLINE_KEY . $userId);
        if (empty($fds)) {
            return [];
        }

        return array_map(static fn (mixed $fd): int => (int) $fd, $fds);
    }

    /** 有没有活着的 WebSocket 连接（docs/05 第 5 节：在线 = 有连接）。 */
    public function isOnline(int $userId): bool
    {
        return $this->fdsOf($userId) !== [];
    }
}
