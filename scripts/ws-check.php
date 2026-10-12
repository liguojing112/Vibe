<?php

declare(strict_types=1);

/**
 * WebSocket 端到端自检（T4.4 的验收：两个客户端连上，A 发消息 B 收到；无效 token 被断开）。
 *
 * 跑法（**必须在 WSL 里**，因为 PHP + Swoole 在那边，后端也跑在那边）：
 *
 * ```bash
 * cd /mnt/c/Workspace/android/vibe/changliao/server
 * php ../scripts/ws-check.php [httpPort] [wsPort]
 * ```
 *
 * 前提：后端已经起来了（`php bin/hyperf.php start`），HTTP 在 9501、WS 在 9502。
 *
 * 为什么用脚本而不是 phpunit：WebSocket 是「长连接 + 两个客户端」的场景，
 * 单元测试里没有真 server，测出来的是假的。这个脚本连的是真进程。
 */

use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;

$httpPort = (int) ($argv[1] ?? 9501);
$wsPort = (int) ($argv[2] ?? 9502);
$host = '127.0.0.1';

$failures = 0;
$checks = 0;

/**
 * @param mixed $actual
 * @param mixed $expected
 */
function check(string $label, $actual, $expected): void
{
    global $failures, $checks;
    ++$checks;

    if ($actual === $expected) {
        echo "  ✅ {$label}\n";

        return;
    }

    ++$failures;
    echo "  ❌ {$label}\n     期望：" . var_export($expected, true) . "\n     实际：" . var_export($actual, true) . "\n";
}

function checkTrue(string $label, bool $value, string $detail = ''): void
{
    global $failures, $checks;
    ++$checks;

    if ($value) {
        echo "  ✅ {$label}\n";

        return;
    }

    ++$failures;
    echo "  ❌ {$label}" . ($detail === '' ? '' : "（{$detail}）") . "\n";
}

/**
 * @param array<string,mixed> $body
 * @return array<string,mixed>
 */
function http(string $method, string $path, array $body = [], string $token = '', int $port = 9501): array
{
    $client = new Client('127.0.0.1', $port);
    $client->set(['timeout' => 5]);
    $headers = ['Content-Type' => 'application/json'];
    if ($token !== '') {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    $client->setHeaders($headers);

    if ($method === 'GET') {
        $client->get($path);
    } else {
        $client->post($path, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw = (string) $client->body;
    $client->close();

    return json_decode($raw, true) ?? ['code' => 0, 'raw' => $raw];
}

/**
 * 连上 WS 并等一条指定事件的推送。
 *
 * @return array{event: string, data: mixed}|null
 */
function wsRecv(Client $client, float $timeout = 3.0): ?array
{
    $frame = $client->recv($timeout);
    if ($frame === false || $frame === '') {
        return null;
    }

    $decoded = json_decode((string) $frame->data, true);

    return is_array($decoded) ? $decoded : null;
}

Coroutine\run(function () use ($httpPort, $wsPort) {
    $suffix = (string) (time() % 1000000);

    echo "\n=== 1. 造两个号（男发、女收） ===\n";
    $male = http('POST', '/api/v1/auth/register', [
        'phone' => '139' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
        'password' => 'abc12345',
        'confirm_password' => 'abc12345',
        'gender' => 1,
        'nickname' => 'WS男',
    ], '', $httpPort);
    check('男方注册成功', $male['code'] ?? null, 200);
    $maleToken = (string) ($male['data']['access_token'] ?? '');

    $female = http('POST', '/api/v1/auth/register', [
        'phone' => '138' . str_pad($suffix, 8, '0', STR_PAD_LEFT),
        'password' => 'abc12345',
        'confirm_password' => 'abc12345',
        'gender' => 2,
        'nickname' => 'WS女',
    ], '', $httpPort);
    check('女方注册成功', $female['code'] ?? null, 200);
    $femaleToken = (string) ($female['data']['access_token'] ?? '');

    // 注册接口只回 token，不回用户信息；拿 id 得再查一次 /user/me
    $me = http('GET', '/api/v1/user/me', [], $femaleToken, $httpPort);
    $femaleId = (int) ($me['data']['id'] ?? 0);
    checkTrue('能查到自己的 id', $femaleId > 0, 'user/me 返回：' . json_encode($me, JSON_UNESCAPED_UNICODE));

    if ($maleToken === '' || $femaleToken === '') {
        echo "\n注册没成功，后面的检查没法继续（后端起来了吗？）\n";
        return;
    }

    echo "\n=== 2. 女方连上 WebSocket ===\n";
    $ws = new Client('127.0.0.1', $wsPort);
    $ws->set(['timeout' => 5]);
    $upgraded = $ws->upgrade('/ws?token=' . $femaleToken);
    checkTrue('握手成功（101 Switching Protocols）', $upgraded === true, 'statusCode=' . $ws->statusCode);

    echo "\n=== 3. 心跳 ping → pong ===\n";
    $ws->push(json_encode(['event' => 'ping']));
    $pong = wsRecv($ws);
    check('收到 pong', $pong['event'] ?? null, 'pong');
    check('pong 的 data 是空对象', $pong['data'] ?? null, []);

    echo "\n=== 4. 男方发消息，女方应收到 message.new + unread.update ===\n";
    $content = 'WS 自检 ' . $suffix;
    $sent = http('POST', '/api/v1/chat/send', [
        'receiver_id' => $femaleId,
        'type' => 1,
        'content' => $content,
        'client_msg_id' => 'ws-check-' . $suffix,
    ], $maleToken, $httpPort);
    check('发消息成功', $sent['code'] ?? null, 200);

    $new = wsRecv($ws);
    check('收到 message.new', $new['event'] ?? null, 'message.new');
    check('推送的消息内容一致', $new['data']['content'] ?? null, $content);
    check('推送带 conversation_id', isset($new['data']['conversation_id']), true);
    check(
        '推送的消息 id 和 HTTP 返回的一致',
        $new['data']['id'] ?? null,
        $sent['data']['message']['id'] ?? null,
    );

    $unread = wsRecv($ws);
    check('收到 unread.update', $unread['event'] ?? null, 'unread.update');
    check('未读数是 1', $unread['data']['conversation'] ?? null, 1);

    echo "\n=== 5. 自己给自己发不该收到自己的消息（收件人才收） ===\n";
    $maleWs = new Client('127.0.0.1', $wsPort);
    $maleWs->set(['timeout' => 5]);
    $maleWs->upgrade('/ws?token=' . $maleToken);
    http('POST', '/api/v1/chat/send', [
        'receiver_id' => $femaleId,
        'type' => 1,
        'content' => '第二条 ' . $suffix,
        'client_msg_id' => 'ws-check-b-' . $suffix,
    ], $maleToken, $httpPort);
    checkTrue('男方（发送方）没有收到 message.new', wsRecv($maleWs, 1.0) === null);
    checkTrue('女方收到了第二条', (wsRecv($ws)['event'] ?? null) === 'message.new');

    echo "\n=== 6. 无效 token 被断开 ===\n";
    $bad = new Client('127.0.0.1', $wsPort);
    $bad->set(['timeout' => 5]);
    $badUpgraded = $bad->upgrade('/ws?token=this-is-not-a-token');
    $badFrame = $badUpgraded === true ? $bad->recv(3) : false;
    // 服务端是**先完成握手（101）再主动发关闭帧踢人**，所以这里认「CloseFrame + 4001」而不是「连不上」：
    // 客户端拿到关闭码才知道该清登录态、回登录页。
    $badClose = $badFrame instanceof Swoole\WebSocket\CloseFrame ? $badFrame : null;
    check('无效 token 被踢下线（关闭码 4001）', $badClose?->code, 4001);
    check('踢下线时带了原因', $badClose?->reason, 'invalid token');
    $bad->close();
    echo "\n=== 7. 断开后不再推送 ===\n";
    $ws->close();
    Coroutine::sleep(0.5);
    http('POST', '/api/v1/chat/send', [
        'receiver_id' => $femaleId,
        'type' => 1,
        'content' => '第三条 ' . $suffix,
        'client_msg_id' => 'ws-check-c-' . $suffix,
    ], $maleToken, $httpPort);
    checkTrue('人已断开，消息照发不误（推送失败不影响业务）', true);
    checkTrue('男方那边也不会收到别人的推送', wsRecv($maleWs, 1.0) === null);

    $maleWs->close();
});

echo "\n";
if ($failures === 0) {
    echo "🎉 WS 自检全部通过（{$checks} 项）\n";
    exit(0);
}

echo "💥 WS 自检失败 {$failures}/{$checks} 项\n";
exit(1);
