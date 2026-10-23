<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\Block;
use App\Model\Cl\Moment;
use App\Model\Cl\MomentComment;
use App\Model\Cl\MomentLike;
use App\Model\Cl\Notice;
use App\Model\Cl\User;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 朋友圈接口（docs/01 P05 之后的扩展）。
 *
 * @internal
 * @coversNothing
 */
final class MomentControllerTest extends TestCase
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
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 发布 ----------

    public function testPublishTextMoment(): void
    {
        $this->loginAs(User::GENDER_MALE);

        $result = $this->post('/api/v1/moment', ['content' => '今天天气不错']);

        self::assertSame(200, $result['code']);
        self::assertSame('今天天气不错', $result['data']['content']);
        self::assertTrue($result['data']['can_delete']);
        self::assertSame(1, Moment::query()->count());
    }

    public function testPublishImageOnlyMoment(): void
    {
        $this->loginAs(User::GENDER_FEMALE);

        $result = $this->post('/api/v1/moment', [
            'images' => ['https://cdn.example.com/1.jpg', 'https://cdn.example.com/2.jpg'],
        ]);

        self::assertSame(200, $result['code']);
        self::assertCount(2, $result['data']['images']);
        self::assertSame('', $result['data']['content']);
    }

    public function testPublishWithoutContentOrImagesRejected(): void
    {
        $this->loginAs(User::GENDER_MALE);

        $result = $this->post('/api/v1/moment', []);

        self::assertNotSame(200, $result['code']);
        self::assertSame(0, Moment::query()->count());
    }

    public function testPublishTooManyImagesRejected(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $images = array_map(static fn (int $i): string => "https://cdn.example.com/{$i}.jpg", range(1, 10));

        $result = $this->post('/api/v1/moment', ['images' => $images]);

        self::assertSame(422, $result['code']);
    }

    // ---------- 浏览 ----------

    public function testUserMomentsVisibleToAnyone(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $this->createMoment($owner->id, '她的动态');

        $result = $this->get('/api/v1/moment/user/' . $owner->id);

        self::assertSame(200, $result['code']);
        self::assertSame(1, $result['data']['total']);
        self::assertSame('她的动态', $result['data']['list'][0]['content']);
        self::assertFalse($result['data']['list'][0]['can_delete']);
    }

    public function testBlockedUserSeesEmptyList(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $this->createMoment($owner->id, '看不到的动态');
        Block::query()->create(['user_id' => $owner->id, 'target_id' => $this->viewerId]);

        $result = $this->get('/api/v1/moment/user/' . $owner->id);

        self::assertSame(200, $result['code']);
        self::assertSame(0, $result['data']['total']);
        self::assertSame([], $result['data']['list']);
    }

    public function testHiddenMomentNotVisible(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '被隐藏');
        $moment->update(['status' => Moment::STATUS_HIDDEN]);

        self::assertSame(0, $this->get('/api/v1/moment/user/' . $owner->id)['data']['total']);
    }

    // ---------- 删除 ----------

    public function testDeleteOwnMoment(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $id = $this->post('/api/v1/moment', ['content' => '待删除'])['data']['id'];

        self::assertSame(200, $this->delete('/api/v1/moment/' . $id)['code']);
        self::assertSame(0, $this->get('/api/v1/moment/mine')['data']['total']);
    }

    public function testCannotDeleteOthersMoment(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '别人的');

        $result = $this->delete('/api/v1/moment/' . $moment->id);

        self::assertNotSame(200, $result['code']);
        self::assertSame(0, Moment::query()->whereNotNull('deleted_at')->count());
    }

    // ---------- 点赞 ----------

    public function testLikeIsIdempotent(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '求赞');

        $first = $this->post('/api/v1/moment/' . $moment->id . '/like');
        $second = $this->post('/api/v1/moment/' . $moment->id . '/like');

        self::assertSame(200, $first['code']);
        self::assertTrue($first['data']['liked']);
        self::assertSame(1, $first['data']['like_count']);
        self::assertSame(1, $second['data']['like_count'], '重复点赞不该重复计数');
        self::assertSame(1, MomentLike::query()->count());
    }

    public function testUnlikeDecrementsCount(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '取消赞');
        $this->post('/api/v1/moment/' . $moment->id . '/like');

        $result = $this->delete('/api/v1/moment/' . $moment->id . '/like');

        self::assertSame(200, $result['code']);
        self::assertFalse($result['data']['liked']);
        self::assertSame(0, $result['data']['like_count']);
        self::assertSame(0, MomentLike::query()->count());
    }

    public function testLikeNotifiesAuthorButNotSelf(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '通知测试');

        $this->post('/api/v1/moment/' . $moment->id . '/like');
        // 再赞自己的（自己发的），不该产生通知
        $own = $this->post('/api/v1/moment', ['content' => '自己的'])['data'];
        $this->post('/api/v1/moment/' . $own['id'] . '/like');

        self::assertSame(1, Notice::query()
            ->where('user_id', $owner->id)
            ->where('biz_type', Notice::BIZ_MOMENT_LIKE)
            ->count());
        self::assertSame(0, Notice::query()
            ->where('user_id', $this->viewerId)
            ->where('biz_type', Notice::BIZ_MOMENT_LIKE)
            ->count());
    }

    // ---------- 评论 ----------

    public function testCommentIncrementsCountAndNotifies(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '评论测试');

        $result = $this->post('/api/v1/moment/' . $moment->id . '/comment', ['content' => '写得真好']);

        self::assertSame(200, $result['code']);
        self::assertSame('写得真好', $result['data']['content']);
        self::assertSame(1, Moment::query()->whereKey($moment->id)->value('comment_count'));
        self::assertSame(1, $this->get('/api/v1/moment/' . $moment->id . '/comment')['data']['total']);
        self::assertSame(1, Notice::query()
            ->where('user_id', $owner->id)
            ->where('biz_type', Notice::BIZ_MOMENT_COMMENT)
            ->count());
    }

    public function testEmptyCommentRejected(): void
    {
        $this->loginAs(User::GENDER_MALE);
        $owner = $this->makeUser(User::GENDER_FEMALE);
        $moment = $this->createMoment($owner->id, '空评论');

        self::assertSame(422, $this->post('/api/v1/moment/' . $moment->id . '/comment', ['content' => ''])['code']);
        self::assertSame(0, MomentComment::query()->count());
    }

    // ---------- 鉴权 ----------

    public function testRequiresLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/moment/mine')['code']);
        self::assertSame(401, $this->client->post('/api/v1/moment', ['content' => 'x'])['code']);
    }

    // ---------- 工具 ----------

    /**
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

    private function createMoment(int $userId, string $content): Moment
    {
        return Moment::query()->create([
            'user_id' => $userId,
            'content' => $content,
            'images' => [],
            'like_count' => 0,
            'comment_count' => 0,
            'status' => Moment::STATUS_NORMAL,
        ]);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function post(string $uri, array $data = []): array
    {
        return $this->client->post($uri, $data, ['Authorization' => 'Bearer ' . $this->viewerToken]);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function delete(string $uri, array $data = []): array
    {
        return $this->client->delete($uri, $data, ['Authorization' => 'Bearer ' . $this->viewerToken]);
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
        MomentLike::query()->delete();
        MomentComment::query()->delete();
        Moment::query()->delete();
        Block::query()->delete();
        Notice::query()->whereIn('biz_type', [Notice::BIZ_MOMENT_LIKE, Notice::BIZ_MOMENT_COMMENT])->delete();
        User::query()->delete();
    }
}
