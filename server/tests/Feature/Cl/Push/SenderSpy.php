<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Push;

use Hyperf\WebSocketServer\Sender;

/**
 * `Sender` 的替身：只把 push / disconnect 的参数记下来，不真往 socket 里写。
 *
 * 为什么需要它：`Sender::push()` / `disconnect()` 是 `__call` 魔术方法（参数个数不固定），
 * 真要 mock 得先 `addMethods`，还要绕过父类构造里对 Swoole server 的依赖，不如直接覆盖 `__call`。
 *
 * 只用于测试，不要放进 app/。
 */
class SenderSpy extends Sender
{
    /** @var array<int,array<int,mixed>> */
    public array $calls = [];

    /** @var array<int,string> 原始 JSON —— 用来查中文有没有被转义 */
    public array $raw = [];

    /** 故意不调父类构造：它要 Swoole server，测试环境里没有，而这里也用不到。 */
    public function __construct() {}

    public function __call(string $name, array $arguments): mixed
    {
        $this->calls[] = $arguments;
        $this->raw[] = (string) ($arguments[1] ?? '');

        return true;
    }
}
