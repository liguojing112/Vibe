<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Redis\Redis;

/**
 * 服务启动时清掉上一轮的在线连接登记。
 *
 * **单机部署**：fd 只在本次进程生命周期内有效，重启后旧 fd 全部作废。
 * 不清的话 Redis 里会留着"假在线"的人和死 fd：
 * `isOnline()` 说这个人在线，推送却打到不存在的连接上，客户端得重进页面才看到消息 ——
 * 表现就是「对方回复了，我这边半天没动静」。
 */
#[Listener]
final class ClearOnlineRegistryListener implements ListenerInterface
{
    public function __construct(
        private readonly Redis $redis,
    ) {}

    public function listen(): array
    {
        return [BeforeMainServerStart::class];
    }

    public function process(object $event): void
    {
        // 在线人数不会太多（每个在线用户 2 个 key），这里用 keys 足够，不必上 SCAN
        foreach (['ws:fd:*', 'online:*'] as $pattern) {
            foreach ((array) $this->redis->keys($pattern) as $key) {
                $this->redis->del((string) $key);
            }
        }
    }
}
