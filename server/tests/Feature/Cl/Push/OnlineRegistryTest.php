<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Push;

use App\Service\Cl\Push\OnlineRegistry;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 在线连接登记（T4.4 / docs/05 第 5 节）。
 *
 * 这张表错了就会「人明明在线却收不到推送」或者「已经断开还一直推」，所以逐个动作都断言死。
 *
 * @internal
 * @coversNothing
 */
final class OnlineRegistryTest extends TestCase
{
    use RunTestsInCoroutine;

    private OnlineRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearKeys();
        $this->registry = make(OnlineRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->clearKeys();
        parent::tearDown();
    }

    public function testAttachMakesTheUserOnlineFromBothDirections(): void
    {
        $this->registry->attach(101, 7);

        self::assertSame(7, $this->registry->userIdOf(101), 'fd → 人');
        self::assertSame([101], $this->registry->fdsOf(7), '人 → fd');
        self::assertTrue($this->registry->isOnline(7));
    }

    public function testOneUserCanHaveSeveralConnections(): void
    {
        $this->registry->attach(101, 7);
        $this->registry->attach(102, 7);

        $fds = $this->registry->fdsOf(7);
        sort($fds);
        self::assertSame([101, 102], $fds, '多设备都要收到推送');
    }

    public function testDetachRemovesBothSides(): void
    {
        $this->registry->attach(101, 7);

        self::assertSame(7, $this->registry->detach(101));
        self::assertNull($this->registry->userIdOf(101));
        self::assertSame([], $this->registry->fdsOf(7));
        self::assertFalse($this->registry->isOnline(7), '集合空了就是不在线，不能留个空集合赖着');
    }

    public function testDetachKeepsTheUserOnlineWhileAnotherConnectionIsAlive(): void
    {
        $this->registry->attach(101, 7);
        $this->registry->attach(102, 7);

        self::assertSame(7, $this->registry->detach(101));

        self::assertTrue($this->registry->isOnline(7), '还有一台设备连着');
        self::assertSame([102], $this->registry->fdsOf(7));
    }

    public function testDetachUnknownFdIsSilent(): void
    {
        self::assertNull($this->registry->detach(999), '重复断开（worker 重启等）不该报错');
        self::assertSame([], $this->registry->fdsOf(7));
        self::assertFalse($this->registry->isOnline(7));
    }

    public function testUnknownUserIsOffline(): void
    {
        self::assertNull($this->registry->userIdOf(12345));
        self::assertSame([], $this->registry->fdsOf(12345));
        self::assertFalse($this->registry->isOnline(12345));
    }

    private function clearKeys(): void
    {
        $redis = make(Redis::class);
        $keys = array_merge($redis->keys('ws:fd:*') ?: [], $redis->keys('online:*') ?: []);
        if ($keys !== []) {
            $redis->del(...$keys);
        }
    }
}
