<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Follow;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Cl\Visit;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 他人主页（docs/05 第 4.2 节 `/user/{id}`）。
 *
 * @internal
 * @coversNothing
 */
final class UserDetailControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $viewerId = 0;

    private string $viewerToken = '';

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->sequence = 0;
        $this->clearAll();
    }

    protected function tearDown(): void
    {
        make(AppConfigService::class)->set('feature.call_enabled', 0);
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 资料 ----------

    public function testDetailReturnsProfileWithDerivedFlags(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE, [
            'nickname' => '小满',
            'city' => '上海',
            'job' => '设计师',
            'bio' => '喜欢逛展',
            'birthday' => Carbon::now()->subYears(26)->toDateString(),
            'real_name_status' => 2,
            'last_active_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
            'level' => 1,
        ]);

        $data = $this->detail($female->id)['data'];

        self::assertSame('小满', $data['nickname']);
        self::assertSame('上海', $data['city']);
        self::assertSame('设计师', $data['job']);
        self::assertSame('喜欢逛展', $data['bio']);
        self::assertSame(26, $data['age']);
        self::assertTrue($data['online']);
        self::assertTrue($data['is_new']);
        self::assertTrue($data['verified']);
    }

    public function testOnlyApprovedPhotosAreVisible(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $approved = $this->makePhoto($female->id, 'https://cdn/a.png', UserPhoto::AUDIT_APPROVED);
        $this->makePhoto($female->id, 'https://cdn/b.png', UserPhoto::AUDIT_PENDING);
        $this->makePhoto($female->id, 'https://cdn/c.png', UserPhoto::AUDIT_REJECTED);

        $photos = $this->detail($female->id)['data']['photos'];

        // 别人只看得到已通过的（待审/驳回的只有本人看得到）
        self::assertCount(1, $photos);
        self::assertSame($approved->id, $photos[0]['id']);
    }

    // ---------- 价格 ----------

    public function testMaleSeesPriceAndFreeHint(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $this->ensureLevel(1, 5);
        $female = $this->makeUser(User::GENDER_FEMALE, ['level' => 1]);

        $price = $this->detail($female->id)['data']['price'];

        self::assertSame(5, $price['message_price']);
        // 还没聊过，对子 3 条免费 → 提示还能免费几条
        self::assertSame('还可免费发送 3 条', $price['free_hint']);
    }

    public function testVoiceVideoPriceHiddenWhenCallDisabled(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);

        $price = $this->detail($female->id)['data']['price'];

        self::assertArrayNotHasKey('voice_price', $price);
        self::assertArrayNotHasKey('video_price', $price);
    }

    public function testVoiceVideoPriceShownWhenCallEnabled(): void
    {
        make(AppConfigService::class)->set('feature.call_enabled', 1);
        $this->loginAs(User::GENDER_MALE);
        $level = $this->ensureLevel(1, 5);
        $female = $this->makeUser(User::GENDER_FEMALE, ['level' => 1]);

        $price = $this->detail($female->id)['data']['price'];

        self::assertSame((int) $level->voice_price, $price['voice_price']);
        self::assertSame((int) $level->video_price, $price['video_price']);
    }

    public function testFemaleViewerGetsNoPrice(): void
    {
        $this->loginAs(User::GENDER_FEMALE);
        $male = $this->makeUser(User::GENDER_MALE);

        // 只有「男看女」才有价格
        self::assertArrayNotHasKey('price', $this->detail($male->id)['data']);
    }

    public function testMutualFollowShowsFreeHint(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        Follow::query()->create(['user_id' => $this->viewerId, 'target_id' => $female->id]);
        Follow::query()->create(['user_id' => $female->id, 'target_id' => $this->viewerId]);

        $data = $this->detail($female->id)['data'];

        self::assertTrue($data['is_mutual']);
        self::assertTrue($data['is_following']);
        self::assertSame('互相关注，私信免费', $data['price']['free_hint']);
    }

    // ---------- 关系 ----------

    public function testFollowState(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);

        self::assertFalse($this->detail($female->id)['data']['is_following']);

        Follow::query()->create(['user_id' => $this->viewerId, 'target_id' => $female->id]);

        $data = $this->detail($female->id)['data'];
        self::assertTrue($data['is_following']);
        self::assertFalse($data['is_mutual']);
    }

    public function testBlockedRelationshipIsMarkedAndHidesPrice(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        Block::query()->create(['user_id' => $female->id, 'target_id' => $this->viewerId]);

        $data = $this->detail($female->id)['data'];

        self::assertTrue($data['is_blocked']);
        // 被拉黑时不显示价格，也不记访客（不然等于告诉对方"我来过"）
        self::assertArrayNotHasKey('price', $data);
        self::assertSame(0, Visit::query()->count());
    }

    // ---------- 访客 ----------

    public function testViewingSomeoneWritesAVisit(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);

        $this->detail($female->id);

        self::assertSame(1, Visit::query()->where('target_id', $female->id)->count());
        // 看两次还是同一条（同一天）
        $this->detail($female->id);
        self::assertSame(1, Visit::query()->where('target_id', $female->id)->count());
    }

    public function testViewingSelfDoesNotWriteAVisit(): void
    {
        $this->loginAs(User::GENDER_MALE);

        $data = $this->detail($this->viewerId)['data'];

        self::assertSame($this->viewerId, $data['id']);
        self::assertFalse($data['is_following']);
        self::assertSame(0, Visit::query()->count());
    }

    // ---------- 可见性 ----------

    public function testInvisibleUsersReturn30001(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $banned = $this->makeUser(User::GENDER_FEMALE, ['status' => User::STATUS_BANNED]);
        $cancelled = $this->makeUser(User::GENDER_FEMALE, ['status' => User::STATUS_CANCELLED]);

        self::assertSame(30001, $this->detail($banned->id)['code']);
        self::assertSame(30001, $this->detail($cancelled->id)['code']);
        self::assertSame(30001, $this->detail(999999)['code']);
    }

    public function testMeRouteStillWorksAfterAddingIdRoute(): void
    {
        // /user/me 不能被 /user/{id} 抢走（静态段优先）
        $this->loginAs(User::GENDER_MALE);

        $result = $this->client->get('/api/v1/user/me', [], $this->auth());

        self::assertSame(200, $result['code']);
        self::assertSame($this->viewerId, $result['data']['id']);
    }

    public function testDetailRequiresLogin(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE);

        self::assertSame(401, $this->client->get('/api/v1/user/' . $female->id)['code']);
    }

    // ---------- 工具 ----------

    /**
     * @param array<string,mixed> $attributes
     */
    private function loginAs(int $gender, array $attributes = []): void
    {
        $phone = '13800000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => '我自己',
        ]);
        self::assertSame(200, $result['code'], '注册失败：' . json_encode($result, JSON_UNESCAPED_UNICODE));

        $this->viewerToken = (string) $result['data']['access_token'];
        $this->viewerId = (int) User::query()->where('phone', $phone)->value('id');

        if ($attributes !== []) {
            User::query()->whereKey($this->viewerId)->update($attributes);
        }
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function makeUser(int $gender, array $attributes = []): User
    {
        $index = ++$this->sequence;

        return User::query()->create(array_merge([
            'account' => 'd' . $index . '_' . $gender,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => '用户' . $index,
            'avatar' => 'https://cdn.example.com/' . $index . '.png',
            'level' => 1,
            'status' => User::STATUS_NORMAL,
        ], $attributes));
    }

    private function makePhoto(int $userId, string $url, int $auditStatus): UserPhoto
    {
        return UserPhoto::query()->create([
            'user_id' => $userId,
            'url' => $url,
            'sort' => 0,
            'audit_status' => $auditStatus,
        ]);
    }

    private function ensureLevel(int $level, int $messagePrice): LevelConfig
    {
        $existing = LevelConfig::query()->where('level', $level)->first();
        if ($existing instanceof LevelConfig) {
            return $existing;
        }

        return LevelConfig::query()->create([
            'level' => $level,
            'name' => 'Lv' . $level,
            'threshold_points' => 0,
            'message_price' => $messagePrice,
            'message_share_rate' => 3000,
            'voice_price' => 60,
            'video_price' => 120,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function detail(int $targetId): array
    {
        return $this->client->get('/api/v1/user/' . $targetId, [], $this->auth());
    }

    /**
     * @return array<string,string>
     */
    private function auth(): array
    {
        return ['Authorization' => 'Bearer ' . $this->viewerToken];
    }

    private function clearAll(): void
    {
        Follow::query()->delete();
        Block::query()->delete();
        Visit::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
