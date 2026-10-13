<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Notice;

use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Follow;
use App\Model\Cl\Notice;
use App\Model\Cl\NoticeRead;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Service\Cl\Notice\NoticeService;
use App\Service\Cl\Relation\RelationService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 系统通知与互动通知（docs/04 第 6 节、`docs/05` 第 4.9 节 `/notice/list`，P10）。
 *
 * 三条规则：
 * - 列表里能看到**发给我的**和**全员公告**；打开列表就标已读；
 * - 未读数 = 一对一未读 + 我还没读过的公告（角标和 `/chat/unread` 一个口径）；
 * - 各业务事件（被关注、收到礼物……）都从这里发通知。
 *
 * @internal
 * @coversNothing
 */
final class NoticeServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private NoticeService $service;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();

        $this->service = make(NoticeService::class);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 发 ----------

    public function testSendWritesNoticeWithCategoryAndBiz(): void
    {
        $user = $this->makeUser(User::GENDER_FEMALE);

        $notice = $this->service->send(
            $user->id,
            Notice::CATEGORY_SYSTEM,
            '等级提升',
            '恭喜你升到 Lv2',
            Notice::BIZ_LEVEL_UP,
            2,
        );

        self::assertSame($user->id, (int) $notice->user_id);
        self::assertSame(Notice::CATEGORY_SYSTEM, (int) $notice->category);
        self::assertSame('等级提升', $notice->title);
        self::assertSame(Notice::BIZ_LEVEL_UP, $notice->biz_type);
        self::assertSame(2, (int) $notice->biz_id);
        self::assertNull($notice->read_at, '刚发出来是未读');
    }

    public function testAnnouncementGoesToEveryone(): void
    {
        $notice = $this->service->announce('平台公告', '本周六维护');

        self::assertSame(Notice::ALL_USERS, (int) $notice->user_id);
        self::assertSame(Notice::BIZ_ANNOUNCE, $notice->biz_type);
    }

    public function testEventHelpersUseTheRightCategoryAndCopy(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');

        $follow = $this->service->followed($female, $male);
        self::assertSame(Notice::CATEGORY_INTERACT, (int) $follow->category);
        self::assertSame('有人关注了你', $follow->title);
        self::assertSame('阿宁 关注了你，去看看吧', $follow->content);
        self::assertSame('FOLLOW', $follow->biz_type);

        $gift = $this->service->giftReceived($female, $male, '一朵玫瑰');
        self::assertSame(Notice::CATEGORY_INTERACT, (int) $gift->category);
        self::assertSame('收到礼物', $gift->title);
        self::assertSame('阿宁 送了你「一朵玫瑰」', $gift->content);

        $audit = $this->service->profileReviewed($female->id, passed: false, fieldLabel: '头像', reason: '照片不清晰');
        self::assertSame(Notice::CATEGORY_SYSTEM, (int) $audit->category);
        self::assertSame('资料审核未通过', $audit->title);
        self::assertSame('你修改的头像未通过审核：照片不清晰', $audit->content);

        $ban = $this->service->banned($male->id, '发布违规内容', 7);
        self::assertSame('账号已被封禁', $ban->title);
        self::assertSame('你的账号已被封禁 7 天：发布违规内容', $ban->content);

        self::assertSame('你的账号已被永久封禁：违规', $this->service->banned($male->id, '违规')->content);
    }

    /** 关注是业务事件：**真的新关注**才发通知，重复点关注不该反复打扰对方。 */
    public function testFollowNotifiesTargetOnlyOnce(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');
        $relation = make(RelationService::class);

        $relation->follow($male, $female->id);
        $relation->follow($male, $female->id);

        self::assertSame(1, Notice::query()->where('user_id', $female->id)->count());
        self::assertSame(0, Notice::query()->where('user_id', $male->id)->count(), '关注的人自己不会收到通知');

        // 取关再关注：会再发一次（是新的关注行为）
        $relation->unfollow($male, $female->id);
        $relation->follow($male, $female->id);
        self::assertSame(2, Notice::query()->where('user_id', $female->id)->count());
    }

    // ---------- 列表与已读 ----------

    public function testListShowsMineAndAnnouncementsAndMarksRead(): void
    {
        $user = $this->makeUser(User::GENDER_FEMALE);
        $other = $this->makeUser(User::GENDER_MALE);
        $this->service->send($user->id, Notice::CATEGORY_SYSTEM, '实名认证已通过');
        $this->service->announce('平台公告', '本周六维护');
        $this->service->send($other->id, Notice::CATEGORY_SYSTEM, '别人的通知');
        $this->service->send($user->id, Notice::CATEGORY_INTERACT, '收到礼物');

        $page = $this->service->list($user, Notice::CATEGORY_SYSTEM, 1);

        self::assertSame(2, $page['total'], '系统通知 = 我的一条 + 公告一条');
        $titles = array_column($page['list'], 'title');
        self::assertContains('实名认证已通过', $titles);
        self::assertContains('平台公告', $titles);
        self::assertNotContains('别人的通知', $titles);
        self::assertNotContains('收到礼物', $titles, '互动通知在另一个分类里');

        // 打开之后系统通知这一分类都算读过了，只剩那条互动通知没读
        self::assertSame(1, $this->service->unreadCount($user->id));
        self::assertSame(1, NoticeRead::query()->where('user_id', $user->id)->count(), '公告补了一条已读记录');

        $again = $this->service->list($user, Notice::CATEGORY_SYSTEM, 1);
        self::assertTrue($again['list'][0]['read'], '第二次打开时公告已经是已读的');
    }

    public function testUnreadCountCoversBothKinds(): void
    {
        $user = $this->makeUser(User::GENDER_FEMALE);

        self::assertSame(0, $this->service->unreadCount($user->id));

        $this->service->send($user->id, Notice::CATEGORY_SYSTEM, '一');
        $this->service->send($user->id, Notice::CATEGORY_INTERACT, '二');
        $this->service->announce('公告一', '');
        $this->service->announce('公告二', '');

        self::assertSame(4, $this->service->unreadCount($user->id), '两条发给我的 + 两条公告');

        // 标已读只影响这个分类：公告两条 + 系统一条，互动那条还留着
        $this->service->markCategoryRead($user->id, Notice::CATEGORY_SYSTEM);
        self::assertSame(1, $this->service->unreadCount($user->id));
    }

    public function testAnnouncementReadIsPerUser(): void
    {
        $first = $this->makeUser(User::GENDER_FEMALE);
        $second = $this->makeUser(User::GENDER_MALE);
        $this->service->announce('平台公告', '本周六维护');

        $this->service->markCategoryRead($first->id, Notice::CATEGORY_SYSTEM);

        self::assertSame(0, $this->service->unreadCount($first->id));
        self::assertSame(1, $this->service->unreadCount($second->id), '别人还没读过这条公告');
    }

    public function testMarkingReadTwiceIsSafe(): void
    {
        $user = $this->makeUser(User::GENDER_FEMALE);
        $this->service->announce('公告', '');

        $this->service->markCategoryRead($user->id, Notice::CATEGORY_SYSTEM);
        $this->service->markCategoryRead($user->id, Notice::CATEGORY_SYSTEM);

        self::assertSame(1, NoticeRead::query()->where('user_id', $user->id)->count(), '唯一索引兜底，不会写重复行');
    }

    public function testListIsPagedNewestFirst(): void
    {
        $user = $this->makeUser(User::GENDER_FEMALE);
        for ($i = 1; $i <= 25; ++$i) {
            $this->service->send($user->id, Notice::CATEGORY_SYSTEM, '通知 ' . $i);
        }

        $page1 = $this->service->list($user, Notice::CATEGORY_SYSTEM, 1);
        $page2 = $this->service->list($user, Notice::CATEGORY_SYSTEM, 2);

        self::assertCount(20, $page1['list']);
        self::assertCount(5, $page2['list']);
        self::assertSame(25, $page1['total']);
        self::assertSame('通知 25', $page1['list'][0]['title'], '最新的在最前');
        self::assertSame('通知 1', $page2['list'][4]['title']);
    }

    // ---------- 工具 ----------

    private function makeUser(int $gender, string $nickname = ''): User
    {
        $index = ++$this->sequence;
        $user = User::query()->create([
            'account' => 'ntuser' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $nickname === '' ? '用户' . $index : $nickname,
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($user->id);
        make(CoinWalletService::class)->createForUser($user->id);

        return $user->refresh();
    }

    private function clearAll(): void
    {
        NoticeRead::query()->delete();
        Notice::query()->delete();
        Follow::query()->delete();
        Block::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
