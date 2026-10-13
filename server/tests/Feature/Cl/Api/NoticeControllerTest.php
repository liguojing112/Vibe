<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Follow;
use App\Model\Cl\Message;
use App\Model\Cl\Notice;
use App\Model\Cl\NoticeRead;
use App\Model\Cl\PointWallet;
use App\Model\Cl\Report;
use App\Model\Cl\User;
use App\Model\Cl\Visit;
use App\Service\Cl\Notice\NoticeService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 通知与举报接口（docs/05 第 4.9 节）：`/notice/list`、`/report`。
 *
 * 另外顺带守住一条：**`/chat/unread` 的 `notice` 不再是写死的 0**
 * （T4.3 建这个字段时通知表还没建，一直占着位）。
 *
 * @internal
 * @coversNothing
 */
final class NoticeControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->clearAll();
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testListReturnsSystemNoticesAndMarksRead(): void
    {
        $user = $this->registerUser(User::GENDER_FEMALE);
        make(NoticeService::class)->send($user->id, Notice::CATEGORY_SYSTEM, '实名认证已通过', '你的身份信息已通过审核');
        make(NoticeService::class)->announce('平台公告', '本周六维护');

        $result = $this->get($user, '/api/v1/notice/list?category=1');

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        $data = $result['data'];
        self::assertSame(2, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertCount(2, $data['list']);
        self::assertSame('平台公告', $data['list'][0]['title'], '最新的在最前');
        self::assertSame(Notice::BIZ_ANNOUNCE, $data['list'][0]['biz_type']);
        self::assertFalse($data['list'][0]['read'], '打开之前公告还没读过');
        self::assertSame(0, $data['unread'], '打开列表之后未读清零');
        self::assertSame(1, NoticeRead::query()->where('user_id', $user->id)->count());
    }

    public function testListCategoryIsRequired(): void
    {
        $user = $this->registerUser(User::GENDER_FEMALE);

        self::assertSame(422, $this->get($user, '/api/v1/notice/list')['code']);
        self::assertSame(422, $this->get($user, '/api/v1/notice/list?category=3')['code']);
    }

    public function testUnreadEndpointCountsNotices(): void
    {
        $user = $this->registerUser(User::GENDER_FEMALE);
        make(NoticeService::class)->send($user->id, Notice::CATEGORY_INTERACT, '有人关注了你');
        make(NoticeService::class)->announce('平台公告', '');

        $result = $this->get($user, '/api/v1/chat/unread');

        self::assertSame(200, $result['code']);
        self::assertSame(2, $result['data']['notice'], '一条互动通知 + 一条没读过的公告');
        self::assertSame(2, $result['data']['total']);

        // 打开系统通知分类后，公告算读过；互动通知还留着
        $this->get($user, '/api/v1/notice/list?category=1');
        self::assertSame(1, $this->get($user, '/api/v1/chat/unread')['data']['notice']);
    }

    // ---------- 举报 ----------

    public function testReportSubmits(): void
    {
        $reporter = $this->registerUser(User::GENDER_MALE);
        $target = $this->registerUser(User::GENDER_FEMALE);

        $result = $this->post($reporter, '/api/v1/report', [
            'target_id' => $target->id,
            'reason' => 'FRAUD',
            'content' => '一上来就让我加微信转账',
            'images' => ['https://cdn.example.com/a.jpg'],
        ]);

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertSame('举报已提交，我们会尽快处理', $result['data']['status_text']);
        self::assertSame(Report::STATUS_PENDING, $result['data']['status']);

        $report = Report::query()->where('reporter_id', $reporter->id)->firstOrFail();
        self::assertSame($target->id, (int) $report->target_id);
        self::assertSame('FRAUD', $report->reason);
        self::assertSame(['https://cdn.example.com/a.jpg'], $report->images);
        self::assertNull($report->message_id);
    }

    public function testReportWithMyOwnMessage(): void
    {
        $reporter = $this->registerUser(User::GENDER_MALE);
        $target = $this->registerUser(User::GENDER_FEMALE);
        $message = $this->addMessage(senderId: $target->id, receiverId: $reporter->id);

        $result = $this->post($reporter, '/api/v1/report', [
            'target_id' => $target->id,
            'reason' => 'ABUSE',
            'message_id' => $message->id,
        ]);

        self::assertSame(200, $result['code']);
        self::assertSame(
            (int) $message->id,
            (int) Report::query()->where('reporter_id', $reporter->id)->value('message_id'),
        );
    }

    public function testReportWithSomeoneElsesMessageIsRejected(): void
    {
        $reporter = $this->registerUser(User::GENDER_MALE);
        $target = $this->registerUser(User::GENDER_FEMALE);
        $other = $this->registerUser(User::GENDER_MALE);
        // 这条消息跟举报人没关系：带上它等于把别人的消息永久钉在库里（保留策略会跳过被举报消息）
        $message = $this->addMessage(senderId: $other->id, receiverId: $target->id);

        $result = $this->post($reporter, '/api/v1/report', [
            'target_id' => $target->id,
            'reason' => 'OTHER',
            'message_id' => $message->id,
        ]);

        self::assertSame(40001, $result['code']);
        self::assertSame('这条消息不在你的会话里', $result['message']);
        self::assertSame(0, Report::query()->where('reporter_id', $reporter->id)->count());
    }

    public function testReportMyselfIsRejected(): void
    {
        $user = $this->registerUser(User::GENDER_MALE);

        $result = $this->post($user, '/api/v1/report', ['target_id' => $user->id, 'reason' => 'OTHER']);

        self::assertSame(40001, $result['code']);
        self::assertSame('不能举报自己', $result['message']);
    }

    public function testReportUnknownTargetIsRejected(): void
    {
        $user = $this->registerUser(User::GENDER_MALE);

        $result = $this->post($user, '/api/v1/report', ['target_id' => 999999, 'reason' => 'OTHER']);

        self::assertSame(30001, $result['code']);
    }

    public function testReportValidatesInput(): void
    {
        $user = $this->registerUser(User::GENDER_MALE);
        $target = $this->registerUser(User::GENDER_FEMALE);

        // 原因只认 5 种
        self::assertSame(422, $this->post($user, '/api/v1/report', ['target_id' => $target->id, 'reason' => 'WHATEVER'])['code']);
        self::assertSame(422, $this->post($user, '/api/v1/report', ['target_id' => $target->id])['code']);
        self::assertSame(422, $this->post($user, '/api/v1/report', ['reason' => 'OTHER'])['code']);
    }

    public function testNeedLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/notice/list?category=1')['code']);
        self::assertSame(401, $this->client->post('/api/v1/report', ['target_id' => 1, 'reason' => 'OTHER'])['code']);
    }

    // ---------- 工具 ----------

    private function addMessage(int $senderId, int $receiverId): Message
    {
        return Message::query()->create([
            'conversation_id' => 1,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'client_msg_id' => 'n' . (++$this->sequence),
            'type' => Message::TYPE_TEXT,
            'content' => '你好',
        ]);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(User $user, string $url, array $body): array
    {
        return $this->client->post($url, $body, ['Authorization' => 'Bearer ' . $this->tokens[$user->id]]);
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], ['Authorization' => 'Bearer ' . $this->tokens[$user->id]]);
    }

    private function registerUser(int $gender): User
    {
        $phone = '13522222' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => '用户' . $this->sequence,
        ]);
        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $this->tokens[$user->id] = (string) $result['data']['access_token'];

        return $user;
    }

    private function clearAll(): void
    {
        NoticeRead::query()->delete();
        Notice::query()->delete();
        Report::query()->delete();
        Message::query()->delete();
        Conversation::query()->delete();
        Visit::query()->delete();
        Follow::query()->delete();
        Block::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
