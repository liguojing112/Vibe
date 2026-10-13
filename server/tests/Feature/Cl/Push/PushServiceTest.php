<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Push;

use App\Model\Cl\Message;
use App\Model\Cl\Notice;
use App\Model\Cl\User;
use App\Service\Cl\Chat\ChatPresenter;
use App\Service\Cl\Chat\ChatQueryService;
use App\Service\Cl\Push\OnlineRegistry;
use App\Service\Cl\Push\PushService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 推送（T4.4 / docs/05 第 5 节）。
 *
 * 这里真正要守住两件事：**下发的 JSON 格式**（安卓端照它解码，写错了是静默收不到）
 * 和 **推不出去不能影响业务**（钱扣了、消息落库了，推送只是锦上添花）。
 *
 * @internal
 * @coversNothing
 */
final class PushServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private SenderSpy $spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearKeys();
        $this->spy = new SenderSpy();
    }

    protected function tearDown(): void
    {
        $this->clearKeys();
        parent::tearDown();
    }

    public function testOfflineUserGetsNoPushAtAll(): void
    {
        self::assertSame(0, $this->push()->toUser(7, PushService::EVENT_MESSAGE_NEW, ['id' => 1]));
        self::assertSame([], $this->spy->calls);
    }

    public function testMessageNewPayloadMatchesTheChatMessagesShape(): void
    {
        $this->registry()->attach(101, 7);
        $message = new Message([
            'id' => 88,
            'conversation_id' => 5,
            'sender_id' => 7,
            'receiver_id' => 9,
            'client_msg_id' => 'c-1',
            'type' => Message::TYPE_TEXT,
            'content' => '你好呀 😀',
            'price_coins' => 5,
            'free_reason' => null,
            'receiver_points' => 15,
            'sensitive_hit' => 0,
            'created_at' => '2026-10-13 10:00:00',
        ]);

        $sent = $this->push()->toUser(
            7,
            PushService::EVENT_MESSAGE_NEW,
            make(ChatPresenter::class)->message($message),
        );

        self::assertSame(1, $sent);
        self::assertSame('message.new', $this->payload(0)['event']);
        self::assertSame(
            [
                'id' => 88,
                'conversation_id' => 5,
                'sender_id' => 7,
                'receiver_id' => 9,
                'client_msg_id' => 'c-1',
                'type' => 1,
                'content' => '你好呀 😀',
                'price_coins' => 5,
                'free_reason' => null,
                'receiver_points' => 15,
                'sensitive_hit' => 0,
                'created_at' => '2026-10-13 10:00:00',
            ],
            $this->payload(0)['data'],
        );

        // 中文不能变成 \uXXXX（JSON_UNESCAPED_UNICODE），否则查日志时看不懂
        self::assertStringContainsString('你好呀', $this->spy->raw[0]);
    }

    public function testUnreadUpdatePayloadMatchesTheUnreadEndpoint(): void
    {
        $user = User::query()->create([
            'account' => 'wsuser1',
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女1',
            'status' => User::STATUS_NORMAL,
        ]);
        $this->registry()->attach(101, $user->id);

        $this->push()->unreadUpdate($user);

        self::assertSame('unread.update', $this->payload(0)['event']);
        self::assertSame(
            ['conversation' => 0, 'visitor' => 0, 'notice' => 0, 'total' => 0],
            $this->payload(0)['data'],
            '结构和 /chat/unread 一模一样，客户端一套解码两处用',
        );

        User::query()->whereKey($user->id)->delete();
    }

    public function testOfflineUserSkipsTheUnreadQuery(): void
    {
        $user = new User(['id' => 123456]);

        $this->push()->unreadUpdate($user);

        self::assertSame([], $this->spy->calls, '不在线就连未读都不查');
    }

    public function testPongIsAnEmptyObject(): void
    {
        $this->registry()->attach(101, 7);

        $this->push()->pong(101);

        self::assertSame('{"event":"pong","data":{}}', $this->spy->raw[0], '空 data 要序列化成 {}，不能是 []');
    }

    public function testNoticeNewPayloadIsSelfContained(): void
    {
        $this->registry()->attach(101, 7);

        $this->push()->noticeNew(new Notice([
            'id' => 12,
            'user_id' => 7,
            'category' => Notice::CATEGORY_INTERACT,
            'title' => '收到礼物',
            'content' => '阿宁 送了你「一朵玫瑰」',
            'biz_type' => 'GIFT',
            'biz_id' => 5,
            'created_at' => '2026-10-14 10:00:00',
        ]));

        self::assertSame('notice.new', $this->payload(0)['event']);
        self::assertSame(
            [
                'id' => 12,
                'category' => 2,
                'title' => '收到礼物',
                'content' => '阿宁 送了你「一朵玫瑰」',
                'biz_type' => 'GIFT',
                'biz_id' => 5,
                'created_at' => '2026-10-14 10:00:00',
            ],
            $this->payload(0)['data'],
            '标题正文都在里面：客户端收到就能直接显示，不用回查',
        );
        self::assertStringContainsString('一朵玫瑰', $this->spy->raw[0]);
    }

    public function testAnnouncementIsNotPushedToAnyone(): void
    {
        $this->registry()->attach(101, 7);

        // 全员公告没有具体收件人（user_id = 0），不能推给随便哪个人
        $this->push()->noticeNew(new Notice([
            'id' => 13,
            'user_id' => Notice::ALL_USERS,
            'category' => Notice::CATEGORY_SYSTEM,
            'title' => '平台公告',
        ]));

        self::assertSame([], $this->spy->calls);
    }

    /**
     * **推不出去不能抛**：fd 是假的（客户端已经断了 / 这条连接不在当前 worker），
     * 真实环境里 `Sender` 会失败甚至抛异常，业务必须照常往下走。
     */
    public function testPushFailureIsSwallowed(): void
    {
        $this->registry()->attach(999999, 7);

        self::assertSame(0, make(PushService::class)->toUser(7, PushService::EVENT_MESSAGE_NEW, ['id' => 1]));
    }

    public function testDisconnectFailureIsSwallowed(): void
    {
        make(PushService::class)->disconnect(999999, PushService::CLOSE_INVALID_TOKEN, 'invalid token');

        self::assertTrue(true, '断开失败只记日志，不往上抛');
    }

    // ---------- 工具 ----------

    /** 用替身 Sender 手工组装，比往容器里塞单例更直白。 */
    private function push(): PushService
    {
        return new PushService(
            $this->spy,
            $this->registry(),
            make(ChatPresenter::class),
            make(ChatQueryService::class),
            make(StdoutLoggerInterface::class),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(int $index): array
    {
        return json_decode($this->spy->raw[$index], true, 512, JSON_THROW_ON_ERROR);
    }

    private function registry(): OnlineRegistry
    {
        return make(OnlineRegistry::class);
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
