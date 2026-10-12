<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\Tag;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Service\Cl\User\ProfileService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * App 资料接口（docs/05 第 4.2 节、docs/01 P14）。
 *
 * 重点：**审核字段提交后，`cl_user` 里仍是旧值（别人看到的），新内容只在 `cl_profile_audit` 里**。
 *
 * @internal
 * @coversNothing
 */
final class UserProfileControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->clearAll();
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- /user/me ----------

    public function testMeRequiresLogin(): void
    {
        $result = $this->client->get('/api/v1/user/me');

        self::assertSame(401, $result['code']);
    }

    public function testMeReturnsProfileWithBalances(): void
    {
        $male = $this->registerMale();

        $result = $this->me($male['token']);

        self::assertSame(200, $result['code']);
        $data = $result['data'];
        self::assertSame($male['user']->id, $data['id']);
        self::assertSame(User::GENDER_MALE, $data['gender']);
        self::assertSame('测试用户', $data['nickname']);
        self::assertSame([], $data['auditing']);
        self::assertSame([], $data['photos']);
        // 男用户有金币余额没有积分余额
        self::assertSame(0, $data['coin_balance']);
        self::assertNull($data['point_balance']);
        self::assertFalse($data['is_vip']);
    }

    public function testMeForFemaleReturnsPointBalance(): void
    {
        $female = $this->register(User::GENDER_FEMALE, '13900139000', '女用户');

        $data = $this->me($female['token'])['data'];

        self::assertNull($data['coin_balance']);
        self::assertSame(0, $data['point_balance']);
    }

    // ---------- 审核字段：本人看到新的，别人看到旧的 ----------

    public function testAvatarGoesToAuditAndOwnerSeesPendingValue(): void
    {
        $male = $this->registerMale();
        $user = $male['user'];
        $oldAvatar = 'https://cdn.example.com/old.png';
        $user->update(['avatar' => $oldAvatar]);

        $result = $this->client->post(
            '/api/v1/user/profile',
            ['avatar' => 'https://cdn.example.com/new.png'],
            $this->auth($male['token']),
        );

        self::assertSame(200, $result['code']);
        // 自己看得到待审的新头像 + 审核中标记
        self::assertSame('https://cdn.example.com/new.png', $result['data']['pending']['avatar']);
        self::assertContains('avatar', $result['data']['auditing']);
        // 但已通过的头像（别人看到的）没变
        self::assertSame($oldAvatar, $result['data']['avatar']);
        self::assertSame($oldAvatar, User::query()->whereKey($user->id)->value('avatar'));

        // 审核表里有一条待审记录，new_value 是新头像
        $audit = ProfileAudit::query()
            ->where('user_id', $user->id)
            ->where('field', ProfileAudit::FIELD_AVATAR)
            ->first();
        self::assertInstanceOf(ProfileAudit::class, $audit);
        self::assertSame(ProfileAudit::STATUS_PENDING, $audit->status);
        self::assertSame('https://cdn.example.com/new.png', $audit->new_value);
        self::assertSame($oldAvatar, $audit->old_value);
    }

    public function testNicknameGoesToAuditToo(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/profile',
            ['nickname' => '新昵称'],
            $this->auth($male['token']),
        );

        self::assertSame('新昵称', $result['data']['pending']['nickname']);
        self::assertSame('测试用户', $result['data']['nickname']);
        self::assertSame('测试用户', User::query()->whereKey($male['user']->id)->value('nickname'));
    }

    public function testResubmitSameFieldKeepsSinglePendingRecord(): void
    {
        $male = $this->registerMale();

        $this->client->post(
            '/api/v1/user/profile',
            ['bio' => '第一次改'],
            $this->auth($male['token']),
        );
        $result = $this->client->post(
            '/api/v1/user/profile',
            ['bio' => '第二次改'],
            $this->auth($male['token']),
        );

        $count = ProfileAudit::query()
            ->where('user_id', $male['user']->id)
            ->where('field', ProfileAudit::FIELD_BIO)
            ->count();
        self::assertSame(1, $count, '同一字段只保留一条待审记录');
        self::assertSame('第二次改', $result['data']['pending']['bio']);
    }

    public function testChangingBackToApprovedValueCancelsAudit(): void
    {
        $male = $this->registerMale();
        $user = $male['user'];

        $this->client->post('/api/v1/user/profile', ['nickname' => '改过的'], $this->auth($male['token']));
        // 再改回原值 → 撤销待审，不让用户白等一次审核
        $result = $this->client->post('/api/v1/user/profile', ['nickname' => '测试用户'], $this->auth($male['token']));

        self::assertSame([], $result['data']['pending']);
        self::assertSame(0, ProfileAudit::query()->where('user_id', $user->id)->count());
    }

    // ---------- 直接生效的字段 ----------

    public function testDirectFieldsApplyImmediately(): void
    {
        $male = $this->registerMale();
        $birthday = Carbon::now()->subYears(20)->toDateString();

        $result = $this->client->post('/api/v1/user/profile', [
            'birthday' => $birthday,
            'city' => '杭州',
            'height' => 172,
            'job' => '产品经理',
            'education' => '本科',
            'dating_purpose' => '认真交友',
        ], $this->auth($male['token']));

        self::assertSame(200, $result['code']);
        self::assertSame($birthday, $result['data']['birthday']);
        self::assertSame(20, $result['data']['age']);
        self::assertSame('杭州', $result['data']['city']);

        $user = User::query()->whereKey($male['user']->id)->first();
        self::assertSame('杭州', $user->city);
        self::assertSame(172, (int) $user->height);
        self::assertSame('产品经理', $user->job);
        // 直接生效的字段不进审核表
        self::assertSame(0, ProfileAudit::query()->where('user_id', $male['user']->id)->count());
    }

    public function testGenderChangeIsIgnored(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/profile',
            ['gender' => User::GENDER_FEMALE, 'nickname' => '照常改昵称'],
            $this->auth($male['token']),
        );

        self::assertSame(200, $result['code']);
        self::assertSame(User::GENDER_MALE, $result['data']['gender']);
        self::assertSame(
            User::GENDER_MALE,
            (int) User::query()->whereKey($male['user']->id)->value('gender'),
        );
    }

    public function testUnder18BirthdayIsRejected(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/profile',
            ['birthday' => Carbon::now()->subYears(17)->toDateString()],
            $this->auth($male['token']),
        );

        self::assertNotSame(200, $result['code']);
        self::assertSame(422, $result['code']);
        self::assertStringContainsString('18', (string) ($result['message'] ?? ''));
    }

    public function testExactly18TodayIsAccepted(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/profile',
            ['birthday' => Carbon::now()->subYears(18)->toDateString()],
            $this->auth($male['token']),
        );

        self::assertSame(200, $result['code']);
        self::assertSame(18, $result['data']['age']);
    }

    public function testTagsMustExistAndBeLimited(): void
    {
        $male = $this->registerMale();
        $tag = Tag::query()->create(['name' => '测试标签', 'sort' => 1, 'enabled' => 1]);

        $ok = $this->client->post('/api/v1/user/profile', ['tags' => [$tag->id]], $this->auth($male['token']));
        self::assertSame(200, $ok['code']);
        self::assertSame([$tag->id], $ok['data']['tags']);

        $tooMany = $this->client->post(
            '/api/v1/user/profile',
            ['tags' => array_fill(1, ProfileService::MAX_TAGS + 1, $tag->id)],
            $this->auth($male['token']),
        );
        self::assertSame(422, $tooMany['code']);

        Tag::query()->whereKey($tag->id)->delete();
    }

    // ---------- 相册 ----------

    public function testPhotosSubmitGoesToAudit(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/photos',
            ['urls' => ['https://cdn.example.com/1.png', 'https://cdn.example.com/2.png']],
            $this->auth($male['token']),
        );

        self::assertSame(200, $result['code']);
        $photos = $result['data']['photos'];
        self::assertCount(2, $photos);
        self::assertSame(0, $photos[0]['audit_status']);
        self::assertSame('https://cdn.example.com/1.png', $photos[0]['url']);
        self::assertSame(1, $photos[1]['sort']);

        self::assertSame(2, UserPhoto::query()->where('user_id', $male['user']->id)->count());
        // cl_profile_audit 里也留一条 photo 记录（后台审核列表用）
        $audit = ProfileAudit::query()
            ->where('user_id', $male['user']->id)
            ->where('field', ProfileAudit::FIELD_PHOTO)
            ->first();
        self::assertInstanceOf(ProfileAudit::class, $audit);
        self::assertSame(implode(',', array_column($photos, 'id')), $audit->new_value);
    }

    public function testPhotosAreDedupedAndEmptyListClearsAlbum(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/photos',
            ['urls' => ['https://cdn.example.com/1.png', 'https://cdn.example.com/1.png', '  ']],
            $this->auth($male['token']),
        );
        self::assertCount(1, $result['data']['photos']);

        $cleared = $this->client->post('/api/v1/user/photos', ['urls' => []], $this->auth($male['token']));
        self::assertSame([], $cleared['data']['photos']);
        self::assertSame(0, UserPhoto::query()->where('user_id', $male['user']->id)->count());
    }

    public function testTooManyPhotosRejected(): void
    {
        $male = $this->registerMale();
        $urls = [];
        for ($i = 0; $i <= ProfileService::MAX_PHOTOS; ++$i) {
            $urls[] = "https://cdn.example.com/{$i}.png";
        }

        $result = $this->client->post('/api/v1/user/photos', ['urls' => $urls], $this->auth($male['token']));

        self::assertSame(422, $result['code']);
    }

    public function testResubmittingSamePhotosDoesNotRestartAudit(): void
    {
        $male = $this->registerMale();
        $urls = ['https://cdn.example.com/1.png'];

        $this->client->post('/api/v1/user/photos', ['urls' => $urls], $this->auth($male['token']));
        $result = $this->client->post('/api/v1/user/photos', ['urls' => $urls], $this->auth($male['token']));

        self::assertCount(1, $result['data']['photos']);
        self::assertSame(1, UserPhoto::query()->where('user_id', $male['user']->id)->count(), '没改就不该重复插入');
    }

    // ---------- 展示开关 ----------

    public function testSettingsApplyImmediately(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/settings',
            ['show_in_nearby' => 0, 'show_in_recommend' => 0],
            $this->auth($male['token']),
        );

        self::assertSame(200, $result['code']);
        $user = User::query()->whereKey($male['user']->id)->first();
        self::assertSame(0, (int) $user->show_in_nearby);
        self::assertSame(0, (int) $user->show_in_recommend);
        self::assertSame([], $this->me($male['token'])['data']['pending']);
    }

    public function testSettingsRejectInvalidValue(): void
    {
        $male = $this->registerMale();

        $result = $this->client->post(
            '/api/v1/user/settings',
            ['show_in_nearby' => 5],
            $this->auth($male['token']),
        );

        self::assertSame(422, $result['code']);
    }

    // ---------- 工具 ----------

    /**
     * @return array{token: string, user: User}
     */
    private function registerMale(): array
    {
        return $this->register(User::GENDER_MALE, '13800138000', '测试用户');
    }

    /**
     * @return array{token: string, user: User}
     */
    private function register(int $gender, string $phone, string $nickname): array
    {
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => $nickname,
        ]);
        self::assertSame(200, $result['code'], '注册失败：' . json_encode($result, JSON_UNESCAPED_UNICODE));

        return [
            'token' => (string) $result['data']['access_token'],
            'user' => User::query()->where('phone', $phone)->firstOrFail(),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * @return array<string,mixed>
     */
    private function me(string $token): array
    {
        return $this->client->get('/api/v1/user/me', [], $this->auth($token));
    }

    private function clearAll(): void
    {
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
