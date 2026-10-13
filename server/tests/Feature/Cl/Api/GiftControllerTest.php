<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Gift;
use App\Model\Cl\GiftRecord;
use App\Model\Cl\Message;
use App\Model\Cl\PlatformIncome;
use App\Model\Cl\PointLog;
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
 * 礼物接口（docs/05 第 4.6 节）：`/gift/list`、`/gift/send`。
 *
 * 计费规则本身在 `GiftBillingTest` 里测，这里只管「接口接对了没有」。
 *
 * @internal
 * @coversNothing
 */
final class GiftControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    /** @var array<int,int> 1 基的礼物 id */
    private array $giftIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->giftIds = [];
        $this->clearAll();
        $this->seedGifts();
        make(AppConfigService::class)->set('share.gift_rate', 3400);
        make(AppConfigService::class)->set('share.unverified_apply_gift', 0);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testGiftListReturnsEnabledGiftsInOrder(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);

        $result = $this->get($male, '/api/v1/gift/list');

        self::assertSame(200, $result['code']);
        $list = $result['data']['list'];
        self::assertCount(6, $list);
        self::assertSame('一朵玫瑰', $list[0]['name']);
        self::assertSame('🌹', $list[0]['icon']);
        self::assertSame(10, $list[0]['price_coins']);
        self::assertSame('心动城堡', $list[5]['name']);
        self::assertSame(680, $list[5]['price_coins']);
    }

    public function testGiftListHidesDisabledGifts(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 0);
        Gift::query()->whereKey($this->giftIds[0])->update(['enabled' => 0]);

        $result = $this->get($male, '/api/v1/gift/list');

        self::assertCount(5, $result['data']['list']);
        self::assertNotContains('一朵玫瑰', array_column($result['data']['list'], 'name'));
    }

    public function testGiftEndpointsRequireLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/gift/list')['code']);
        // 参数先过校验中间件（全局的），所以这里带上合法参数才走得到鉴权那一步
        self::assertSame(401, $this->client->post('/api/v1/gift/send', [
            'receiver_id' => 1,
            'gift_id' => 1,
            'client_order_id' => 'no-auth',
        ])['code']);
    }

    public function testSendGiftReturnsMessageAndBalance(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 500);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $result = $this->send($male, $female->id, $this->giftIds[3]);

        self::assertSame(200, $result['code']);
        self::assertSame('浪漫花束', $result['data']['message']['content']);
        self::assertSame(Message::TYPE_GIFT, $result['data']['message']['type']);
        self::assertSame(0, $result['data']['message']['price_coins']);
        self::assertSame(500 - 120, $result['data']['balance']);
    }

    public function testSendGiftValidatesInput(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 500);

        // 缺 client_order_id
        $result = $this->client->post('/api/v1/gift/send', [
            'receiver_id' => 1,
            'gift_id' => $this->giftIds[0],
        ], $this->headers($male));

        self::assertSame(422, $result['code']);
        self::assertSame(0, GiftRecord::query()->count());
    }

    public function testSendGiftWithoutEnoughCoinsReturns20001(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 10);
        $female = $this->registerUser(User::GENDER_FEMALE, 0);

        $result = $this->send($male, $female->id, $this->giftIds[5]);

        self::assertSame(20001, $result['code']);
        self::assertSame(10, $result['data']['balance']);
        self::assertSame(0, Message::query()->count());
    }

    public function testSendGiftToMaleIsRejected(): void
    {
        $male = $this->registerUser(User::GENDER_MALE, 500);
        $otherMale = $this->registerUser(User::GENDER_MALE, 0);

        $result = $this->send($male, $otherMale->id, $this->giftIds[0]);

        self::assertSame(30003, $result['code']);
    }

    // ---------- 工具 ----------

    private function registerUser(int $gender, int $coins): User
    {
        $phone = '13400000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
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
            make(CoinWalletService::class)->increase($user->id, $coins, CoinLogType::RECHARGE, 'test', $user->id, '测试充值');
        }

        return $user;
    }

    /**
     * @return array<string,mixed>
     */
    private function send(User $sender, int $receiverId, int $giftId): array
    {
        return $this->client->post('/api/v1/gift/send', [
            'receiver_id' => $receiverId,
            'gift_id' => $giftId,
            'client_order_id' => 'o-' . (++$this->sequence),
        ], $this->headers($sender));
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], $this->headers($user));
    }

    /**
     * @return array<string,string>
     */
    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokens[$user->id]];
    }

    private function seedGifts(): void
    {
        $rows = [
            ['name' => '一朵玫瑰', 'icon' => '🌹', 'price_coins' => 10, 'sort' => 1],
            ['name' => '热拿铁', 'icon' => '☕', 'price_coins' => 30, 'sort' => 2],
            ['name' => '心动气球', 'icon' => '🎈', 'price_coins' => 60, 'sort' => 3],
            ['name' => '浪漫花束', 'icon' => '💐', 'price_coins' => 120, 'sort' => 4],
            ['name' => '星河许愿', 'icon' => '🌠', 'price_coins' => 300, 'sort' => 5],
            ['name' => '心动城堡', 'icon' => '🏰', 'price_coins' => 680, 'sort' => 6],
        ];

        foreach ($rows as $row) {
            $this->giftIds[] = (int) Gift::query()->create(array_merge($row, ['enabled' => 1]))->id;
        }
    }

    private function clearAll(): void
    {
        PlatformIncome::query()->delete();
        GiftRecord::query()->delete();
        Gift::query()->delete();
        CoinLog::query()->delete();
        PointLog::query()->delete();
        Message::query()->delete();
        \App\Model\Cl\Conversation::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
