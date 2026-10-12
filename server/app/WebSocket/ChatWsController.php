<?php

declare(strict_types=1);

namespace App\WebSocket;

use App\Model\Cl\User;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Push\OnlineRegistry;
use App\Service\Cl\Push\PushService;
use App\Service\Cl\Push\WsAuthenticator;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Throwable;

/**
 * 聊天实时连接（docs/05 第 5 节）。
 *
 * **只推不收**：App 端只发心跳 `{"event":"ping"}`，发消息一律走 `POST /chat/send`。
 * 所以这里没有「收了消息去落库」的逻辑，收到不认识的事件直接忽略（客户端版本比服务端新时别炸）。
 *
 * 三个回调对应三条硬要求：
 * - `onOpen`：**token 无效直接断开**（带 4001 关闭码）；有效就登记 fd → userId
 * - `onMessage`：ping → pong
 * - `onClose`：注销登记 + 更新 `last_active_at`（docs/05 第 5 节：断开后更新最后活跃）
 */
final class ChatWsController implements OnOpenInterface, OnMessageInterface, OnCloseInterface
{
    public function __construct(
        private readonly WsAuthenticator $authenticator,
        private readonly OnlineRegistry $registry,
        private readonly PushService $pushService,
        private readonly UserRepository $userRepository,
        private readonly StdoutLoggerInterface $logger,
    ) {}

    public function onOpen($server, $request): void
    {
        $fd = (int) $request->fd;
        $token = is_array($request->get) ? (string) ($request->get['token'] ?? '') : '';

        $user = $this->authenticator->resolve($token);
        if (! $user instanceof User) {
            $this->logger->info(sprintf('[ws] fd[%d] token 无效，断开', $fd));
            $this->pushService->disconnect($fd, PushService::CLOSE_INVALID_TOKEN, 'invalid token');

            return;
        }

        $this->registry->attach($fd, $user->id);
        $this->touch($user->id);
        $this->logger->info(sprintf('[ws] fd[%d] 用户 %d 上线', $fd, $user->id));
    }

    public function onMessage($server, $frame): void
    {
        $fd = (int) $frame->fd;
        $payload = json_decode((string) $frame->data, true);

        if (! is_array($payload)) {
            return;
        }

        if (($payload['event'] ?? '') === 'ping') {
            $this->pushService->pong($fd);
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $userId = $this->registry->detach($fd);
        if ($userId === null) {
            return;
        }

        $this->logger->info(sprintf('[ws] fd[%d] 用户 %d 断开', $fd, $userId));

        // 还有别的连接（多设备）就说明人还在线，别把最后活跃时间改掉
        if (! $this->registry->isOnline($userId)) {
            $this->touch($userId);
        }
    }

    /**
     * 记一次活跃。用一条 UPDATE 就够，不必把整个 User 读出来再 save（推送路径上省一次查询）。
     */
    private function touch(int $userId): void
    {
        try {
            // 直接写库而不是走 UserRepository::updateById（那会先 select 一次）
            $this->userRepository->getQuery()
                ->whereKey($userId)
                ->update(['last_active_at' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            // 活跃时间只是展示用，写失败不该影响连接
            $this->logger->error(sprintf('[ws] 更新用户 %d 最后活跃失败：%s', $userId, $e->getMessage()));
        }
    }
}
