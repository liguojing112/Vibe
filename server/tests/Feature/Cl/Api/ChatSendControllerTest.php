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
use App\Model\Enums\Cl\CoinLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * `POST /chat/send` 走 HTTP 的这一层（docs/05 第 4.5 节）：鉴权、参数校验、业务错误的透传。
 *
 * 计费规则本身在 `MessageBillingTest` 里逐个场景测，这里只管「接口接对了没有」。
 *
 * @internal
 * @coversNothing
 */
final class ChatSendControllerTest extends TestCase
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
        // 有用例会把免费条数改成 0，收尾必须还原，不然会污染后面的用例（踩过）
        make(AppConfigService::class)->set('chat.free_count_per_pair', 3);
        $this->clearAll();
        parent::tearDown();
    }

    public function testSendReturnsMessageAndFee(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $result = $this->send($male, $female->id, '你好呀');

        self::assertSame(200, $result['code']);
        self::assertSame('你好呀', $result['data']['message']['content']);
        self::assertSame(0, $result['data']['message']['price_coins'], '前几条免费');

        // fee 结构按 docs/05 第 4.5 节给全（收费条文案由服务端拼）
        $fee = $result['data']['fee'];
        self::assertNotNull($fee);
        self::assertSame(0, $fee['next_price']);
        self::assertSame('PAIR_FREE', $fee['free_reason']);
        self::assertSame(2, $fee['pair_free_left']);
        self::assertSame(100, $fee['balance']);
        self::assertSame('还可免费发送 2 条 · 之后 5 币/条 · 余额 100 币', $fee['strip_text']);
    }

    public function testSendRequiresLogin(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $result = $this->client->post('/api/v1/chat/send', [
            'receiver_id' => $female->id,
            'type' => Message::TYPE_TEXT,
            'content' => '在吗',
            'client_msg_id' => 'no-auth',
        ]);

        self::assertSame(401, $result['code']);
    }

    public function testInvalidTypeIsRejected(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $result = $this->send($male, $female->id, '礼物走别的接口', type: 3);

        self::assertSame(422, $result['code']);
        self::assertSame(0, Message::query()->count());
    }

    public function testSameGenderIsRejected(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 100);
        $otherMale = $this->registerUser(User::GENDER_MALE, 0);

        $result = $this->send($male, $otherMale->id, '哥们');

        self::assertSame(30003, $result['code']);
    }

    public function testNotEnoughCoinsReturns20001(): void
    {
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        $male = $this->registerUser(User::GENDER_MALE, 4);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $result = $this->send($male, $female->id, '钱不够');

        self::assertSame(20001, $result['code']);
        self::assertSame(4, $result['data']['balance']);
        self::assertSame(0, Message::query()->count());
    }

    // ---------- 工具 ----------

    /**
     * 用注册接口造人（顺便建钱包、拿 token），再给他充点金币。
     */
    private function registerUser(int $gender, int $coins): User
    {
        $phone = '13700000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
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

    /**
     * @return array<string,mixed>
     */
    private function send(User $sender, int $receiverId, string $content, int $type = Message::TYPE_TEXT): array
    {
        return $this->client->post('/api/v1/chat/send', [
            'receiver_id' => $receiverId,
            'type' => $type,
            'content' => $content,
            'client_msg_id' => 'c-' . (++$this->sequence),
        ], ['Authorization' => 'Bearer ' . $this->tokens[$sender->id]]);
    }

    private function clearAll(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
