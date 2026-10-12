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
 * 发现接口（docs/05 第 4.3 节、docs/01 P01）。
 *
 * @internal
 * @coversNothing
 */
final class DiscoverControllerTest extends TestCase
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

    // ---------- 可见性规则 ----------

    public function testOnlyOppositeGenderIsReturned(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $femaleA = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '小满']);
        $femaleB = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '晚晴']);
        $this->makeUser(User::GENDER_MALE, ['nickname' => '阿宁']);

        $ids = $this->ids($this->discover());

        self::assertSame(2, $this->discover()['data']['total']);
        self::assertContains($femaleA->id, $ids);
        self::assertContains($femaleB->id, $ids);
        self::assertNotContains($this->viewerId, $ids);
    }

    public function testFemaleViewerSeesOnlyMales(): void
    {
        $this->loginAs(User::GENDER_FEMALE);
        $male = $this->makeUser(User::GENDER_MALE);
        $this->makeUser(User::GENDER_FEMALE);

        $ids = $this->ids($this->discover());

        self::assertSame([$male->id], $ids);
    }

    public function testBannedAndCancelledUsersAreHidden(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $normal = $this->makeUser(User::GENDER_FEMALE);
        $this->makeUser(User::GENDER_FEMALE, ['status' => User::STATUS_BANNED]);
        $this->makeUser(User::GENDER_FEMALE, ['status' => User::STATUS_CANCELLED]);

        self::assertSame([$normal->id], $this->ids($this->discover()));
    }

    public function testUsersWithoutAvatarAreHidden(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $withAvatar = $this->makeUser(User::GENDER_FEMALE);
        $this->makeUser(User::GENDER_FEMALE, ['avatar' => '']);

        // 没头像 = 资料没过审，不出现（docs/01 P01）
        self::assertSame([$withAvatar->id], $this->ids($this->discover()));
    }

    public function testBlockedUsersAreHiddenInBothDirections(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $blockedByMe = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '我拉黑的']);
        $blockedMe = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '拉黑我的']);
        $visible = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '正常的']);

        $this->block($this->viewerId, $blockedByMe->id);
        $this->block($blockedMe->id, $this->viewerId);

        self::assertSame([$visible->id], $this->ids($this->discover()));
    }

    public function testShowInRecommendSwitchOnlyAffectsRecommendTab(): void
    {
        // 「我」要有城市，「同城」tab 才有内容
        $this->loginAs(User::GENDER_MALE, ['city' => '上海']);
        $hidden = $this->makeUser(User::GENDER_FEMALE, ['show_in_recommend' => 0]);
        $normal = $this->makeUser(User::GENDER_FEMALE);

        self::assertSame([$normal->id], $this->ids($this->discover()));

        // 「新人」「同城」不看这个开关
        self::assertCount(2, $this->ids($this->discover(['tab' => 'new'])));
        self::assertCount(2, $this->ids($this->discover(['tab' => 'city'])));
        self::assertContains($hidden->id, $this->ids($this->discover(['tab' => 'new'])));
    }

    // ---------- 三个 tab ----------

    public function testNewTabOnlyShowsRecentRegistrations(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $fresh = $this->makeUser(User::GENDER_FEMALE);
        $old = $this->makeUser(User::GENDER_FEMALE);
        // 8 天前注册，超过「新人」的 7 天
        User::query()->whereKey($old->id)->update([
            'created_at' => Carbon::now()->subDays(8)->toDateTimeString(),
        ]);

        self::assertSame([$fresh->id], $this->ids($this->discover(['tab' => 'new'])));
    }

    public function testCityTabOnlyShowsSameCity(): void
    {
        $this->loginAs(User::GENDER_MALE, ['city' => '上海']);
        $same = $this->makeUser(User::GENDER_FEMALE, ['city' => '上海']);
        $this->makeUser(User::GENDER_FEMALE, ['city' => '杭州']);

        self::assertSame([$same->id], $this->ids($this->discover(['tab' => 'city'])));
    }

    public function testCityTabIsEmptyWhenViewerHasNoCity(): void
    {
        $this->loginAs(User::GENDER_MALE, ['city' => '']);
        $this->makeUser(User::GENDER_FEMALE, ['city' => '上海']);

        self::assertSame(0, $this->discover(['tab' => 'city'])['data']['total']);
    }

    public function testInvalidTabRejected(): void
    {
        $this->loginAs(User::GENDER_MALE);

        $result = $this->get('/api/v1/discover', ['tab' => 'video']);

        self::assertSame(422, $result['code']);
    }

    // ---------- 筛选 ----------

    public function testAgeFilterWorks(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $young = $this->makeUser(User::GENDER_FEMALE, [
            'birthday' => Carbon::now()->subYears(25)->toDateString(),
        ]);
        $this->makeUser(User::GENDER_FEMALE, [
            'birthday' => Carbon::now()->subYears(45)->toDateString(),
        ]);

        $ids = $this->ids($this->discover(['age_min' => 20, 'age_max' => 30]));

        self::assertSame([$young->id], $ids);
    }

    public function testCityFilterWorks(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $hangzhou = $this->makeUser(User::GENDER_FEMALE, ['city' => '杭州']);
        $this->makeUser(User::GENDER_FEMALE, ['city' => '上海']);

        self::assertSame([$hangzhou->id], $this->ids($this->discover(['city' => '杭州'])));
    }

    public function testPurposeFilterWorks(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $serious = $this->makeUser(User::GENDER_FEMALE, ['dating_purpose' => '认真交友']);
        $this->makeUser(User::GENDER_FEMALE, ['dating_purpose' => '兴趣搭子']);

        self::assertSame([$serious->id], $this->ids($this->discover(['purpose' => ['认真交友']])));
    }

    public function testVerifiedFilterWorks(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $verified = $this->makeUser(User::GENDER_FEMALE, ['real_name_status' => 2]);
        $this->makeUser(User::GENDER_FEMALE, ['real_name_status' => 0]);

        self::assertSame([$verified->id], $this->ids($this->discover(['verified' => 1])));
    }

    public function testCompleteFilterWorks(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $complete = $this->makeUser(User::GENDER_FEMALE, [
            'bio' => '喜欢逛展',
            'birthday' => Carbon::now()->subYears(26)->toDateString(),
        ]);
        $this->makeUser(User::GENDER_FEMALE, ['bio' => '']);

        self::assertSame([$complete->id], $this->ids($this->discover(['complete' => 1])));
    }

    public function testOnlineOnlyFilterWorks(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $online = $this->makeUser(User::GENDER_FEMALE, [
            'last_active_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
        ]);
        $this->makeUser(User::GENDER_FEMALE, [
            'last_active_at' => Carbon::now()->subHours(3)->toDateTimeString(),
        ]);

        self::assertSame([$online->id], $this->ids($this->discover(['online_only' => 1])));
    }

    public function testOnlineFlagIsDerived(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $this->makeUser(User::GENDER_FEMALE, [
            'last_active_at' => Carbon::now()->subMinutes(2)->toDateTimeString(),
        ]);
        $this->makeUser(User::GENDER_FEMALE, [
            'last_active_at' => Carbon::now()->subHours(3)->toDateTimeString(),
        ]);

        $onlineFlags = array_column($this->discover()['data']['list'], 'online');

        self::assertSame([true, false], $onlineFlags);
    }

    // ---------- 价格 ----------

    public function testMaleSeesMessagePriceOfFemaleLevel(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $level = $this->levelThree();
        $this->makeUser(User::GENDER_FEMALE, ['level' => 3]);

        $item = $this->discover()['data']['list'][0];

        self::assertSame((int) $level->message_price, $item['message_price']);
    }

    public function testVoiceVideoPriceHiddenWhenCallDisabled(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $this->levelThree();
        $this->makeUser(User::GENDER_FEMALE, ['level' => 3]);

        $item = $this->discover()['data']['list'][0];

        // 语音/视频开关默认关：不返回这两项（docs/01 第 4 节）
        self::assertArrayNotHasKey('voice_price', $item);
        self::assertArrayNotHasKey('video_price', $item);
    }

    public function testVoiceVideoPriceShownWhenCallEnabled(): void
    {
        make(AppConfigService::class)->set('feature.call_enabled', 1);
        $this->loginAs(User::GENDER_MALE);
        $level = $this->levelThree();
        $this->makeUser(User::GENDER_FEMALE, ['level' => 3]);

        $item = $this->discover()['data']['list'][0];

        self::assertSame((int) $level->voice_price, $item['voice_price']);
        self::assertSame((int) $level->video_price, $item['video_price']);
    }

    public function testFemaleViewerGetsNoPrice(): void
    {
        $this->loginAs(User::GENDER_FEMALE);
        $this->makeUser(User::GENDER_MALE, ['level' => 3]);

        $item = $this->discover()['data']['list'][0];

        self::assertArrayNotHasKey('message_price', $item);
    }

    // ---------- 分页 ----------

    public function testPagination(): void
    {
        $this->loginAs(User::GENDER_MALE);
        for ($i = 0; $i < 25; ++$i) {
            $this->makeUser(User::GENDER_FEMALE);
        }

        $first = $this->discover()['data'];
        self::assertSame(25, $first['total']);
        self::assertCount(20, $first['list']);
        self::assertSame(1, $first['page']);
        self::assertSame(20, $first['page_size']);
        self::assertTrue($first['has_more']);

        $second = $this->discover(['page' => 2])['data'];
        self::assertCount(5, $second['list']);
        self::assertFalse($second['has_more']);
    }

    // ---------- 搜索 ----------

    public function testSearchByNickname(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $target = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '小满']);
        $this->makeUser(User::GENDER_FEMALE, ['nickname' => '晚晴']);

        $result = $this->get('/api/v1/discover/search', ['keyword' => '小满']);

        self::assertSame(1, $result['data']['total']);
        self::assertSame($target->id, $result['data']['list'][0]['id']);
    }

    public function testSearchById(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $target = $this->makeUser(User::GENDER_FEMALE, ['nickname' => '小满']);

        $result = $this->get('/api/v1/discover/search', ['keyword' => (string) $target->id]);

        self::assertContains($target->id, array_column($result['data']['list'], 'id'));
    }

    public function testSearchOnlyOppositeGender(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $this->makeUser(User::GENDER_MALE, ['nickname' => '阿宁']);

        // 同性即使昵称命中也不返回
        self::assertSame(0, $this->get('/api/v1/discover/search', ['keyword' => '阿宁'])['data']['total']);
    }

    public function testSearchWithoutKeywordRejected(): void
    {
        $this->loginAs(User::GENDER_MALE);

        self::assertSame(422, $this->get('/api/v1/discover/search')['code']);
    }

    // ---------- 鉴权 ----------

    public function testDiscoverRequiresLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/discover')['code']);
        self::assertSame(401, $this->client->get('/api/v1/discover/search', ['keyword' => 'x'])['code']);
    }

    // ---------- 工具 ----------

    /**
     * 登录态的「我」用注册接口造（注册不限流，登录限流），其它用户直接建表更快。
     *
     * @param array<string,mixed> $attributes
     */
    private function loginAs(int $gender, array $attributes = []): void
    {
        $phone = '13900000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
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
            'account' => 'u' . $index . '_' . $gender,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => '用户' . $index,
            'avatar' => 'https://cdn.example.com/' . $index . '.png',
            'city' => '上海',
            'status' => User::STATUS_NORMAL,
            'show_in_nearby' => 1,
            'show_in_recommend' => 1,
        ], $attributes));
    }

    /**
     * 等级 3 的配置行：已经有就用现成的（别改测试库里别的用例依赖的数据）。
     */
    private function levelThree(): LevelConfig
    {
        $level = LevelConfig::query()->where('level', 3)->first();
        if ($level instanceof LevelConfig) {
            return $level;
        }

        return LevelConfig::query()->create([
            'level' => 3,
            'name' => 'Lv3',
            'threshold_points' => 100000,
            'message_price' => 10,
            'message_share_rate' => 4000,
            'voice_price' => 90,
            'video_price' => 180,
        ]);
    }

    private function block(int $userId, int $targetId): void
    {
        Block::query()->create(['user_id' => $userId, 'target_id' => $targetId]);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function discover(array $query = []): array
    {
        return $this->get('/api/v1/discover', $query);
    }

    /**
     * @param array<string,mixed> $result
     * @return array<int,int>
     */
    private function ids(array $result): array
    {
        return array_column($result['data']['list'], 'id');
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private function get(string $uri, array $query = []): array
    {
        return $this->client->get($uri, $query, ['Authorization' => 'Bearer ' . $this->viewerToken]);
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
