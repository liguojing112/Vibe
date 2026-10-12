<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Message;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Cl\Visit;
use App\Model\Enums\Cl\CoinLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 聊天查询这一层的 HTTP 接口（docs/05 第 4.5 节）：会话、消息、已读、未读、sync、fee-rules。
 *
 * 业务规则在 `ChatQueryTest` 里测，这里只管「路由接对了、鉴权挡住了、返回结构是约定好的那个」。
 *
 * @internal
 * @coversNothing
 */
final class ChatQueryControllerTest extends TestCase
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
        make(AppConfigService::class)->set('chat.free_count_per_pair', 3);
    }

    protected function tearDown(): void
    {
        make(AppConfigService::class)->set('chat.free_count_per_pair', 3);
        $this->clearAll();
        parent::tearDown();
    }

    public function testConversationsRequireLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/chat/conversations')['code']);
        self::assertSame(401, $this->client->get('/api/v1/chat/unread')['code']);
        self::assertSame(401, $this->client->get('/api/v1/chat/sync')['code']);
        self::assertSame(401, $this->client->get('/api/v1/chat/fee-rules')['code']);
    }

    public function testConversationListAndUnreadAndSync(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);
        $this->send($male, $female->id, '你好');

        $list = $this->get($female, '/api/v1/chat/conversations');
        self::assertSame(200, $list['code']);
        self::assertSame(1, $list['data']['total']);
        self::assertSame($male->id, $list['data']['list'][0]['peer']['id']);
        self::assertSame(1, $list['data']['list'][0]['unread']);
        self::assertSame('你好', $list['data']['list'][0]['last_message']['content']);
        self::assertSame(20, $list['data']['page_size']);

        $unread = $this->get($female, '/api/v1/chat/unread');
        self::assertSame(1, $unread['data']['conversation']);
        self::assertSame(0, $unread['data']['visitor']);
        self::assertSame(1, $unread['data']['total']);

        $sync = $this->get($female, '/api/v1/chat/sync?after_id=0');
        self::assertSame(200, $sync['code']);
        self::assertCount(1, $sync['data']['list']);
        self::assertSame((int) $sync['data']['list'][0]['id'], $sync['data']['max_id']);
        self::assertFalse($sync['data']['has_more']);
    }

    public function testWithReturnsConversationIdAndFee(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        // 还没聊过：conversation_id 是 null，会后端第一次发消息才建
        $before = $this->get($male, '/api/v1/chat/conversations/with/' . $female->id);
        self::assertSame(200, $before['code']);
        self::assertNull($before['data']['conversation_id']);
        self::assertSame(3, $before['data']['fee']['pair_free_left']);

        $this->send($male, $female->id, '你好');

        $after = $this->get($male, '/api/v1/chat/conversations/with/' . $female->id);
        self::assertNotNull($after['data']['conversation_id']);
        self::assertSame('还可免费发送 2 条 · 之后 5 币/条 · 余额 100 币', $after['data']['fee']['strip_text']);

        // 女用户：没有任何价格信息
        $femaleView = $this->get($female, '/api/v1/chat/conversations/with/' . $male->id);
        self::assertNull($femaleView['data']['fee']);
    }

    public function testMessagesPagingAndRead(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);
        $this->send($male, $female->id, '一');
        $this->send($male, $female->id, '二');

        $conversationId = $this->conversationIdOf($male, $female);

        $messages = $this->get($female, '/api/v1/chat/messages?conversation_id=' . $conversationId . '&limit=1');
        self::assertSame(200, $messages['code']);
        self::assertTrue($messages['data']['has_more']);
        self::assertCount(1, $messages['data']['list']);
        self::assertSame('二', $messages['data']['list'][0]['content']);

        $older = $this->get(
            $female,
            '/api/v1/chat/messages?conversation_id=' . $conversationId . '&before_id=' . $messages['data']['list'][0]['id'],
        );
        self::assertFalse($older['data']['has_more']);
        self::assertSame('一', $older['data']['list'][0]['content']);

        $read = $this->post($female, '/api/v1/chat/read', ['conversation_id' => $conversationId]);
        self::assertSame(200, $read['code']);
        self::assertSame(0, $read['data']['conversation'], '已读接口直接把新的未读总览带回来');
        self::assertSame(0, $this->get($female, '/api/v1/chat/unread')['data']['conversation']);
    }

    public function testReadRequiresConversationId(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);

        self::assertSame(422, $this->post($male, '/api/v1/chat/read', [])['code']);
    }

    public function testMessagesOfForeignConversationIsNotFound(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);
        $stranger = $this->registerUser(User::GENDER_FEMALE, 0);
        $this->send($male, $female->id, '你好');

        $result = $this->get(
            $stranger,
            '/api/v1/chat/messages?conversation_id=' . $this->conversationIdOf($male, $female),
        );

        self::assertSame(30001, $result['code']);
    }

    public function testDeleteConversationThenNewMessageBringsItBack(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);
        $this->send($male, $female->id, '你好');
        $conversationId = $this->conversationIdOf($male, $female);

        $deleted = $this->client->delete('/api/v1/chat/conversations/' . $conversationId, [], $this->headers($female));
        self::assertSame(200, $deleted['code']);
        self::assertSame(0, $this->get($female, '/api/v1/chat/conversations')['data']['total']);

        $this->send($male, $female->id, '在吗');

        $list = $this->get($female, '/api/v1/chat/conversations')['data'];
        self::assertSame(1, $list['total']);
        self::assertSame('在吗', $list['list'][0]['last_message']['content']);
    }

    public function testFeeRulesAreGeneratedFromConfig(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $maleItems = $this->get($male, '/api/v1/chat/fee-rules')['data']['items'];
        self::assertContains('每对用户之间，前 3 条私信免费', $maleItems);
        self::assertContains('互相关注后，私信免费', $maleItems);

        $femaleItems = $this->get($female, '/api/v1/chat/fee-rules')['data']['items'];
        self::assertSame('女用户发送私信免费，不扣金币', $femaleItems[0]);
        self::assertStringContainsString('分成', implode("\n", $femaleItems));
    }

    // ---------- 工具 ----------

    private function registerUser(int $gender, int $coins): User
    {
        $phone = '13600000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
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

        if ($coins > 0) {
            make(CoinWalletService::class)->increase(
                $user->id,
                $coins,
                CoinLogType::RECHARGE,
                'test',
                $user->id,
                '测试充值',
            );
        }

        return $user;
    }

    private function send(User $sender, int $receiverId, string $content): array
    {
        return $this->post($sender, '/api/v1/chat/send', [
            'receiver_id' => $receiverId,
            'type' => Message::TYPE_TEXT,
            'content' => $content,
            'client_msg_id' => 'c-' . (++$this->sequence),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], $this->headers($user));
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function post(User $user, string $url, array $data): array
    {
        return $this->client->post($url, $data, $this->headers($user));
    }

    /**
     * @return array<string,string>
     */
    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokens[$user->id]];
    }

    private function conversationIdOf(User $a, User $b): int
    {
        [$low, $high] = Conversation::pair($a->id, $b->id);

        return (int) Conversation::query()
            ->where('user_low_id', $low)
            ->where('user_high_id', $high)
            ->value('id');
    }

    private function clearAll(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();
        Visit::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
