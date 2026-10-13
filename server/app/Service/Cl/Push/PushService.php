<?php

declare(strict_types=1);

namespace App\Service\Cl\Push;

use App\Model\Cl\Message;
use App\Model\Cl\Notice;
use App\Model\Cl\User;
use App\Service\Cl\Chat\ChatPresenter;
use App\Service\Cl\Chat\ChatQueryService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\WebSocketServer\Sender;
use Throwable;

/**
 * 实时推送（docs/05 第 5 节）。**所有服务端 → App 的推送都从这里出**，别处不准直接用 `Sender`。
 *
 * 三条纪律：
 * 1. **推送失败绝不能影响业务**：发消息的钱已经扣了、消息已经落库了，推送只是锦上添花，
 *    所以每次 push 都吞异常只记日志 —— 客户端掉线了靠 `/chat/sync` 补拉，不能因为推不出去就报错。
 * 2. **不在线就别推**：没有连接时直接返回，不做无谓的 Redis / DB 查询。
 * 3. **组包只认 `ChatPresenter`**：保证推送里的消息结构和 `/chat/messages` 里的一模一样。
 */
final class PushService
{
    public const EVENT_MESSAGE_NEW = 'message.new';

    public const EVENT_UNREAD_UPDATE = 'unread.update';

    public const EVENT_NOTICE_NEW = 'notice.new';

    public const EVENT_PONG = 'pong';

    /** 无效 token 断开时的业务关闭码（4000~4999 是应用自定义区间）。 */
    public const CLOSE_INVALID_TOKEN = 4001;

    public function __construct(
        private readonly Sender $sender,
        private readonly OnlineRegistry $registry,
        private readonly ChatPresenter $presenter,
        private readonly ChatQueryService $queryService,
        private readonly StdoutLoggerInterface $logger,
    ) {}

    /**
     * 推给一个人的**所有**连接（多设备都收）。
     *
     * @return int 实际推出去的连接数
     */
    public function toUser(int $userId, string $event, array|object $data): int
    {
        $fds = $this->registry->fdsOf($userId);
        if ($fds === []) {
            return 0;
        }

        $payload = $this->encode($event, $data);
        $sent = 0;
        foreach ($fds as $fd) {
            if ($this->send($fd, $payload)) {
                ++$sent;
            }
        }

        return $sent;
    }

    /**
     * 有人收到新消息：先推消息本身，再推一次未读总览（客户端拿它刷角标）。
     *
     * 消息要**事务提交之后**再推（调用方负责），否则客户端可能先收到推送、再查到「这条消息不存在」。
     */
    public function messageReceived(Message $message, User $receiver): void
    {
        if (! $this->registry->isOnline($receiver->id)) {
            return;
        }

        $this->toUser($receiver->id, self::EVENT_MESSAGE_NEW, $this->presenter->message($message));
        $this->unreadUpdate($receiver);
    }

    /** 未读总览变了（结构和 `/chat/unread` 完全一致）。 */
    public function unreadUpdate(User $user): void
    {
        if (! $this->registry->isOnline($user->id)) {
            return;
        }

        try {
            $unread = $this->queryService->unread($user);
        } catch (Throwable $e) {
            $this->logger->error('[ws] 查询未读失败，跳过 unread.update：' . $e->getMessage());

            return;
        }

        $this->toUser($user->id, self::EVENT_UNREAD_UPDATE, $unread);
    }

    /**
     * 收到一条系统/互动通知（P10 的红点）。
     *
     * 载荷是**自包含**的：标题、正文、分类都在里面，客户端不用回查就能直接显示。
     */
    public function noticeNew(Notice $notice): void
    {
        $userId = (int) $notice->user_id;
        if ($userId === Notice::ALL_USERS || ! $this->registry->isOnline($userId)) {
            return;
        }

        $this->toUser($userId, self::EVENT_NOTICE_NEW, [
            'id' => (int) $notice->id,
            'category' => (int) $notice->category,
            'title' => (string) $notice->title,
            'content' => (string) $notice->content,
            'biz_type' => $notice->biz_type,
            'biz_id' => $notice->biz_id === null ? null : (int) $notice->biz_id,
            'created_at' => $notice->created_at?->toDateTimeString(),
        ]);
    }

    /** 心跳应答（`pong` 的 data 是空对象，客户端按 `{}` 解更省事）。 */
    public function pong(int $fd): void
    {
        $this->send($fd, $this->encode(self::EVENT_PONG, (object) []));
    }

    /**
     * 踢掉一条连接（token 无效时用）。用 `Sender` 而不是直接拿 `$server`，
     * 是因为它统一处理了「跨 worker / 协程服务器」的差异。
     */
    public function disconnect(int $fd, int $code, string $reason): void
    {
        try {
            $this->sender->disconnect($fd, $code, $reason);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('[ws] 断开 fd[%d] 失败：%s', $fd, $e->getMessage()));
        }
    }

    private function send(int $fd, string $payload): bool
    {
        try {
            $this->sender->push($fd, $payload);

            return true;
        } catch (Throwable $e) {
            // 客户端已经断了 / 这个 fd 不在本 worker，都算「推不出去」，不往上抛
            $this->logger->warning(sprintf('[ws] 推送 fd[%d] 失败：%s', $fd, $e->getMessage()));

            return false;
        }
    }

    private function encode(string $event, array|object $data): string
    {
        // JSON_UNESCAPED_UNICODE：中文昵称、消息内容原样下发，别变成 \uXXXX
        return (string) json_encode(['event' => $event, 'data' => $data], JSON_UNESCAPED_UNICODE);
    }
}
