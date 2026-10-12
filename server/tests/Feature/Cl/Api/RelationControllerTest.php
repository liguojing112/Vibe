<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Follow;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Cl\Visit;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Relation\RelationService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * App 关系接口（docs/05 第 4.4 节、docs/01 第 7 节）。
 *
 * @internal
 * @coversNothing
 */
final class RelationControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    /** 注册时拿到的 token 按用户 id 存着（别在测试里反复登录，会撞登录限流）。 */
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
        // 开关可能被某个用例改过，恢复默认
        make(AppConfigService::class)->set('vip.female_free_view', 1);
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 关注 / 互关 ----------

    public function testFollowAndUnfollow(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);

        $followed = $this->follow($a, $b);
        self::assertSame(200, $followed['code']);
        // 关注后立刻返回关系快照，页面不用自己猜是不是互关了
        self::assertTrue($followed['data']['is_following']);
        self::assertFalse($followed['data']['is_mutual']);
        self::assertSame(1, Follow::query()->where('user_id', $a)->where('target_id', $b)->count());

        // 重复关注不会插两条
        self::assertSame(200, $this->follow($a, $b)['code']);
        self::assertSame(1, Follow::query()->where('user_id', $a)->count());

        $unfollowed = $this->post('/api/v1/relation/unfollow', ['target_id' => $b], $a);
        self::assertSame(200, $unfollowed['code']);
        self::assertFalse($unfollowed['data']['is_following']);
        self::assertFalse($unfollowed['data']['is_mutual']);
        self::assertSame(0, Follow::query()->where('user_id', $a)->count());
    }

    public function testFollowReturnsMutualWhenOtherSideAlreadyFollows(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);
        $this->follow($b, $a);

        $result = $this->follow($a, $b);

        self::assertTrue($result['data']['is_following']);
        self::assertTrue($result['data']['is_mutual'], '对方已经关注我了，回关就是互关');
    }

    public function testMutualFollowShowsUpAsFriends(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);

        $this->follow($a, $b);
        $this->follow($b, $a);

        $friends = $this->get('/api/v1/relation/friends', $a)['data'];
        self::assertSame(1, $friends['total']);
        self::assertSame($b, $friends['list'][0]['id']);
        self::assertTrue($friends['list'][0]['is_mutual']);
        self::assertTrue($friends['list'][0]['is_following']);
    }

    public function testSingleFollowIsNotMutual(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);

        // A 单方面关注 B
        $this->follow($a, $b);

        // A 的「我关注的」里有 B，但不是互关
        $following = $this->get('/api/v1/relation/following', $a)['data'];
        self::assertSame(1, $following['total']);
        self::assertFalse($following['list'][0]['is_mutual']);

        // A 的好友列表是空的
        self::assertSame(0, $this->get('/api/v1/relation/friends', $a)['data']['total']);

        // B 的「喜欢我的人」里有 A，且 A 还没关注 B？——A 关注了 B，所以 B 看 A 时 is_following 为 false（B 没关注 A）
        $fans = $this->get('/api/v1/relation/fans', $b)['data'];
        self::assertSame(1, $fans['total']);
        self::assertSame($a, $fans['list'][0]['id']);
        self::assertFalse($fans['list'][0]['is_following']);
    }

    public function testCannotFollowSelf(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);

        $result = $this->follow($a, $a);

        self::assertNotSame(200, $result['code']);
        self::assertSame(0, Follow::query()->count());
    }

    public function testFollowUnknownUserReturns30001(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);

        self::assertSame(30001, $this->follow($a, 999999)['code']);
    }

    // ---------- 拉黑后互相不可见 ----------

    public function testBlockHidesBothSidesFromEveryList(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);

        // 先互相关注 + B 访问 A，把两边列表都填上
        $this->follow($a, $b);
        $this->follow($b, $a);
        $this->visit($b, $a);

        self::assertSame(1, $this->get('/api/v1/relation/friends', $a)['data']['total']);
        self::assertSame(1, $this->get('/api/v1/relation/visitors', $a)['data']['total']);

        // A 拉黑 B
        self::assertSame(200, $this->post('/api/v1/relation/block', ['target_id' => $b], $a)['code']);
        self::assertSame(1, Block::query()->where('user_id', $a)->where('target_id', $b)->count());

        // 双方在对方的各种列表里都不再出现
        self::assertSame(0, $this->get('/api/v1/relation/friends', $a)['data']['total']);
        self::assertSame(0, $this->get('/api/v1/relation/following', $a)['data']['total']);
        self::assertSame([], $this->get('/api/v1/relation/fans', $a)['data']['list'], '粉丝列表里不该有 B');
        self::assertSame([], $this->get('/api/v1/relation/visitors', $a)['data']['list']);
        self::assertSame([], $this->get('/api/v1/relation/friends', $b)['data']['list']);
        self::assertSame([], $this->get('/api/v1/relation/following', $b)['data']['list']);

        // 黑名单里能看到 B
        $blocks = $this->get('/api/v1/relation/blocks', $a)['data'];
        self::assertSame(1, $blocks['total']);
        self::assertSame($b, $blocks['list'][0]['id']);
    }

    public function testBlockedUserCannotFollowBack(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);

        $this->post('/api/v1/relation/block', ['target_id' => $b], $a);

        // B 关注 A 时当 A 不存在（不暴露「你被拉黑了」）
        self::assertSame(30001, $this->follow($b, $a)['code']);
        self::assertSame(0, Follow::query()->count());
    }

    public function testUnblockRestoresVisibility(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);
        $this->follow($a, $b);
        $this->post('/api/v1/relation/block', ['target_id' => $b], $a);

        self::assertSame(200, $this->post('/api/v1/relation/unblock', ['target_id' => $b], $a)['code']);

        self::assertSame(1, $this->get('/api/v1/relation/following', $a)['data']['total']);
    }

    // ---------- 访客 ----------

    public function testSameDayRepeatVisitKeepsOneRecord(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);

        $this->visit($b, $a);
        $first = Visit::query()->where('target_id', $a)->first();
        $firstAt = $first->last_visit_at;

        $this->visit($b, $a);

        self::assertSame(1, Visit::query()->where('target_id', $a)->count(), '同一天同一个人只算一条');
        $again = Visit::query()->where('target_id', $a)->firstOrFail();
        self::assertTrue($again->last_visit_at->greaterThanOrEqualTo($firstAt), '重复访问应刷新最近访问时间');
        self::assertSame(1, $this->get('/api/v1/relation/visitors', $a)['data']['total']);
    }

    public function testVisitSelfIsNotRecorded(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);

        $this->visit($a, $a);

        self::assertSame(0, Visit::query()->count());
    }

    public function testReadingVisitorsClearsUnread(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);
        $this->visit($b, $a);

        self::assertSame(1, $this->me($a)['data']['stats']['unread_visitors']);

        $this->get('/api/v1/relation/visitors', $a);

        self::assertSame(0, $this->me($a)['data']['stats']['unread_visitors'], '读过之后未读清零');
    }

    // ---------- 打码 ----------

    public function testMaleNonVipGetsMaskedFans(): void
    {
        $male = $this->registerUser('13800138001', User::GENDER_MALE, '男用户');
        $female = $this->registerUser('13800138002', User::GENDER_FEMALE, '小满');
        $this->follow($female, $male);

        $fans = $this->get('/api/v1/relation/fans', $male)['data'];

        self::assertTrue($fans['locked'], '非会员男用户应拿到锁定标记');
        self::assertSame('小*', $fans['list'][0]['nickname'], '昵称只留首字 + *');
        self::assertTrue($fans['list'][0]['avatar_blur']);
        // 打码只影响展示字段，id 还要给（回关要用）
        self::assertSame($female, $fans['list'][0]['id']);
    }

    public function testMaleVipSeesFans(): void
    {
        $male = $this->registerUser('13800138001', User::GENDER_MALE);
        $female = $this->registerUser('13800138002', User::GENDER_FEMALE, '小满');
        $this->follow($female, $male);
        $this->makeVip($male);

        $fans = $this->get('/api/v1/relation/fans', $male)['data'];

        self::assertFalse($fans['locked']);
        self::assertSame('小满', $fans['list'][0]['nickname']);
        self::assertFalse($fans['list'][0]['avatar_blur']);
    }

    public function testFemaleSeesFansByDefault(): void
    {
        $female = $this->registerUser('13800138002', User::GENDER_FEMALE, '小满');
        $male = $this->registerUser('13800138001', User::GENDER_MALE, '阿宁');
        $this->follow($male, $female);

        $fans = $this->get('/api/v1/relation/fans', $female)['data'];

        // vip.female_free_view 默认 1：女用户不开会员也能看完整访客/喜欢我
        self::assertFalse($fans['locked']);
        self::assertSame('阿宁', $fans['list'][0]['nickname']);
    }

    public function testFemaleIsMaskedWhenSwitchOff(): void
    {
        $female = $this->registerUser('13800138002', User::GENDER_FEMALE, '小满');
        $male = $this->registerUser('13800138001', User::GENDER_MALE, '阿宁');
        $this->follow($male, $female);
        make(AppConfigService::class)->set('vip.female_free_view', 0);

        $fans = $this->get('/api/v1/relation/fans', $female)['data'];

        self::assertTrue($fans['locked']);
        self::assertSame('阿*', $fans['list'][0]['nickname']);
    }

    public function testVisitorsAreMaskedToo(): void
    {
        $male = $this->registerUser('13800138001', User::GENDER_MALE);
        $female = $this->registerUser('13800138002', User::GENDER_FEMALE, '小满');
        $this->visit($female, $male);

        $visitors = $this->get('/api/v1/relation/visitors', $male)['data'];

        self::assertTrue($visitors['locked']);
        self::assertSame('小*', $visitors['list'][0]['nickname']);
        self::assertNotNull($visitors['list'][0]['visited_at']);
    }

    public function testFollowingListIsNotMasked(): void
    {
        $male = $this->registerUser('13800138001', User::GENDER_MALE);
        $female = $this->registerUser('13800138002', User::GENDER_FEMALE, '小满');
        $this->follow($male, $female);

        $following = $this->get('/api/v1/relation/following', $male)['data'];

        // 自己关注的人不打码（打码只管「喜欢我的人 / 最近访客」）
        self::assertFalse($following['locked']);
        self::assertSame('小满', $following['list'][0]['nickname']);
    }

    // ---------- 统计 ----------

    public function testMeReturnsRelationStats(): void
    {
        $a = $this->registerUser('13800138001', User::GENDER_MALE);
        $b = $this->registerUser('13800138002', User::GENDER_FEMALE);
        $c = $this->registerUser('13800138003', User::GENDER_FEMALE);

        $this->follow($a, $b);
        $this->follow($b, $a);
        $this->follow($a, $c);
        $this->follow($c, $a);
        $this->visit($b, $a);

        $stats = $this->me($a)['data']['stats'];

        self::assertSame(2, $stats['following'], '我关注的');
        self::assertSame(2, $stats['friends'], '好友（互关）');
        self::assertSame(2, $stats['fans'], '关注我的');
        self::assertSame(1, $stats['visitors'], '访客人数');
        self::assertSame(1, $stats['unread_visitors']);
    }

    // ---------- 鉴权 ----------

    public function testRelationApisRequireLogin(): void
    {
        self::assertSame(401, $this->client->post('/api/v1/relation/follow', ['target_id' => 1])['code']);
        self::assertSame(401, $this->client->get('/api/v1/relation/following')['code']);
        self::assertSame(401, $this->client->get('/api/v1/relation/visitors')['code']);
    }

    // ---------- 工具 ----------

    private function registerUser(string $phone, int $gender, string $nickname = '测试用户'): int
    {
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => $nickname,
        ]);
        self::assertSame(200, $result['code'], '注册失败：' . json_encode($result, JSON_UNESCAPED_UNICODE));

        $userId = (int) User::query()->where('phone', $phone)->value('id');
        $this->tokens[$userId] = (string) $result['data']['access_token'];

        return $userId;
    }

    private function makeVip(int $userId): void
    {
        User::query()->whereKey($userId)->update(['vip_expire_at' => Carbon::now()->addDays(30)]);
    }

    /**
     * @return array<string,mixed>
     */
    private function follow(int $from, int $to): array
    {
        return $this->post('/api/v1/relation/follow', ['target_id' => $to], $from);
    }

    private function visit(int $visitorId, int $targetId): void
    {
        // 访客记录发生在「打开别人的主页」时（T3.6 的 /user/{id}），这里直接调 service 模拟
        make(RelationService::class)->recordVisit($visitorId, $targetId);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(string $uri, array $body, int $userId): array
    {
        return $this->client->post($uri, $body, $this->auth($userId));
    }

    /**
     * @return array<string,mixed>
     */
    private function get(string $uri, int $userId): array
    {
        return $this->client->get($uri, [], $this->auth($userId));
    }

    /**
     * @return array<string,mixed>
     */
    private function me(int $userId): array
    {
        return $this->get('/api/v1/user/me', $userId);
    }

    /**
     * @return array<string,string>
     */
    private function auth(int $userId): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokens[$userId]];
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
