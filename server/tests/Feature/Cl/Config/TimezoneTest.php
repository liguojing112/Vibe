<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Config;

use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 时区（docs/06 第 3.2 节：会员每日免费额度按 **Asia/Shanghai 自然日** 重置）。
 *
 * 为什么要有这个用例：PHP 默认是 UTC，而 MySQL 用的是宿主机的时区（本机 +08）。
 * 两边不一致时，「在线」「今天」这类判断会整体偏 8 小时 ——
 * 开发时真的踩过：用户 2 小时没活跃还显示「在线」。
 *
 * 注意 phpunit 的 `tests/bootstrap.php` 里自己也设了时区，
 * 所以**光断言运行时值抓不到「入口文件里那行被删掉」**，这里额外看一眼 `bin/hyperf.php`。
 *
 * @internal
 * @coversNothing
 */
final class TimezoneTest extends TestCase
{
    use RunTestsInCoroutine;

    public function testRuntimeTimezoneIsShanghai(): void
    {
        self::assertSame('Asia/Shanghai', date_default_timezone_get());
    }

    public function testEntryFileSetsShanghaiTimezone(): void
    {
        $entry = (string) file_get_contents(BASE_PATH . '/bin/hyperf.php');

        self::assertStringContainsString('date_default_timezone_set', $entry);
        self::assertStringContainsString('Asia/Shanghai', $entry);
    }
}
