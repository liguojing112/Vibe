<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Chat;

use App\Exception\ClBusinessException;
use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Follow;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Message;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\SensitiveWord;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Cl\Visit;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Repository\Cl\VisitRepository;
use App\Service\Cl\Chat\ChatQueryService;
use App\Service\Cl\Chat\MessageBillingService;
use App\Service\Cl\Chat\SensitiveWordService;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 会话、消息查询与已读（docs/05 第 4.5 节 / T4.3）。
 *
 * 三个验收点在这里断言死：**未读数正确**、**删除后有新消息会重新出现**、**女用户的 fee 为 null**。
 *
 * @internal
 * @coversNothing
 */
final class ChatQueryTest extends TestCase
{
    use RunTestsInCoroutine;

    private ChatQueryService $query;

    private MessageBillingService $billing;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->resetConfig();
        $this->seedLevels();

        $this->query = make(ChatQueryService::class);
        $this->billing = make(MessageBillingService::class);
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 会话列表 ----------

    public function testConversationListCarriesPeerLastMessageAndUnread(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '你好');

        $list = $this->query->conversations($female, 1)['list'];

        self::assertCount(1, $list);
        self::assertSame($male->id, $list[0]['peer']['id']);
        self::assertSame($male->nickname, $list[0]['peer']['nickname']);
        self::assertSame($male->avatar, $list[0]['peer']['avatar']);
        self::assertSame('你好', $list[0]['last_message']['content']);
        self::assertSame($male->id, $list[0]['last_message']['sender_id']);
        self::assertSame(1, $list[0]['unread'], '女方未读 1');

        // 自己发的那条不给自己加未读
        $maleList = $this->query->conversations($male, 1)['list'];
        self::assertSame(0, $maleList[0]['unread']);
    }

    /** 未读数：每人只数「发给自己的」条数（验收项）。 */
    public function testUnreadIsCountedPerSide(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '一');
        $this->send($male, $female, '二');
        $this->send($female, $male, '回你一条');

        $unreadForFemale = $this->query->unread($female);
        self::assertSame(2, $unreadForFemale['conversation']);
        self::assertSame(2, $unreadForFemale['total']);

        $unreadForMale = $this->query->unread($male);
        self::assertSame(1, $unreadForMale['conversation']);
    }

    public function testUnreadIncludesVisitorsSeparately(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $stranger = $this->makeMale();
        $this->send($male, $female, '你好');
        // 两个人今天都看过她
        make(VisitRepository::class)->record($male->id, $female->id);
        make(VisitRepository::class)->record($stranger->id, $female->id);

        $unread = $this->query->unread($female);

        self::assertSame(1, $unread['conversation']);
        self::assertSame(2, $unread['visitor'], '访客是单独一类，不混进会话未读');
        self::assertSame(0, $unread['notice'], '系统通知表还没建（P6），先恒为 0');
        self::assertSame(3, $unread['total']);
    }

    public function testUnreadCountsResetAfterMarkRead(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '一');
        $this->send($male, $female, '二');
        $conversationId = $this->conversationIdOf($male, $female);

        $unread = $this->query->markRead($female, $conversationId);

        self::assertSame(0, $unread['conversation']);
        self::assertSame(0, $unread['total']);
        self::assertSame(0, $this->query->conversations($female, 1)['list'][0]['unread']);
        // 监管留痕：收到的两条都打上了 read_at
        self::assertSame(0, Message::query()->where('receiver_id', $female->id)->whereNull('read_at')->count());
    }

    public function testMarkReadRejectsForeignConversation(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $stranger = $this->makeFemale();
        $this->send($male, $female, '你好');
        $conversationId = $this->conversationIdOf($male, $female);

        $this->expectBizCode(BizCode::USER_NOT_FOUND, fn () => $this->query->markRead($stranger, $conversationId));
    }

    // ---------- 删除会话 ----------

    public function testDeleteConversationOnlyHidesItForMe(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '你好');
        $conversationId = $this->conversationIdOf($male, $female);

        $this->query->deleteConversation($female, $conversationId);

        self::assertSame([], $this->query->conversations($female, 1)['list']);
        self::assertSame(0, $this->query->conversations($female, 1)['total']);
        self::assertCount(1, $this->query->conversations($male, 1)['list'], '对方那边照旧');
        // 消息没被删，只是对她隐藏
        self::assertSame(1, Message::query()->count());
    }

    /** 验收项：删掉之后对方再来消息，会话要重新出现（且带新的未读）。 */
    public function testDeletedConversationReappearsOnNewMessage(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '你好');
        $conversationId = $this->conversationIdOf($male, $female);

        $this->query->deleteConversation($female, $conversationId);
        self::assertSame([], $this->query->conversations($female, 1)['list']);

        $this->send($male, $female, '在吗');

        $list = $this->query->conversations($female, 1)['list'];
        self::assertCount(1, $list);
        self::assertSame('在吗', $list[0]['last_message']['content']);
        self::assertSame(1, $list[0]['unread'], '重新出现时未读从 0 重新数，不是把删之前的也捡回来');
    }

    public function testDeleteConversationClearsUnreadAndHidesFromUnreadTotal(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '一');
        $this->send($male, $female, '二');
        $conversationId = $this->conversationIdOf($male, $female);

        $this->query->deleteConversation($female, $conversationId);

        self::assertSame(0, $this->query->unread($female)['conversation'], '角标不该指向一个列表里看不到的会话');
    }

    public function testDeleteConversationIsIdempotent(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '你好');
        $conversationId = $this->conversationIdOf($male, $female);

        $this->query->deleteConversation($female, $conversationId);
        $this->query->deleteConversation($female, $conversationId);

        self::assertSame([], $this->query->conversations($female, 1)['list']);
    }

    // ---------- 拉黑 / 分页 ----------

    public function testConversationListExcludesBlockedPeers(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '你好');

        make(BlockRepository::class)->add($female->id, $male->id);

        self::assertSame([], $this->query->conversations($female, 1)['list'], '我拉黑的人不出现');
        self::assertSame([], $this->query->conversations($male, 1)['list'], '拉黑我的人也不出现');
    }

    public function testConversationListIsNewestFirstAndPagedByConstant(): void
    {
        $male = $this->makeMale(balance: 500);
        $females = [];
        for ($i = 0; $i < 3; ++$i) {
            $female = $this->makeFemale();
            $this->send($male, $female, "第 {$i} 条");
            $females[] = $female;
        }

        $page1 = $this->query->conversations($male, 1);
        self::assertSame(3, $page1['total']);
        self::assertCount(3, $page1['list']);
        self::assertSame(
            [$females[2]->id, $females[1]->id, $females[0]->id],
            array_column(array_column($page1['list'], 'peer'), 'id'),
            '最近聊过的排在最前',
        );

        // 一页装得下就一页给完；翻到第 2 页是空的但 total 不变
        $page2 = $this->query->conversations($male, 2);
        self::assertSame([], $page2['list']);
        self::assertSame(3, $page2['total']);
    }

    // ---------- 进聊天页 with/{userId} ----------

    public function testWithReturnsNullConversationIdBeforeFirstMessage(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();

        $result = $this->query->with($male, $female->id);

        self::assertNull($result['conversation_id']);
        self::assertSame($female->id, $result['peer']['id']);
        self::assertSame(0, $result['unread']);
        self::assertSame(3, $result['fee']['pair_free_left'], '还没聊过，免费条数一条没动');
    }

    public function testWithReturnsFeeForMale(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '第一条');

        $result = $this->query->with($male, $female->id);

        self::assertSame($this->conversationIdOf($male, $female), $result['conversation_id']);
        self::assertSame(0, $result['fee']['next_price']);
        self::assertSame('PAIR_FREE', $result['fee']['free_reason']);
        self::assertSame(2, $result['fee']['pair_free_left']);
        self::assertSame(100, $result['fee']['balance']);
        self::assertSame('还可免费发送 2 条 · 之后 5 币/条 · 余额 100 币', $result['fee']['strip_text']);
    }

    /** 验收项：女用户没有收费信息。 */
    public function testWithReturnsNullFeeForFemale(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '你好');

        $result = $this->query->with($female, $male->id);

        self::assertNull($result['fee'], '女用户不显示任何价格信息');
        self::assertSame(1, $result['unread']);
    }

    public function testWithRejectsSameGenderAndBlockedPeer(): void
    {
        $male = $this->makeMale(balance: 100);
        $otherMale = $this->makeMale();
        $female = $this->makeFemale();

        $this->expectBizCode(BizCode::SAME_GENDER_NOT_ALLOWED, fn () => $this->query->with($male, $otherMale->id));

        make(BlockRepository::class)->add($female->id, $male->id);
        // 拉黑后互相不可见：就当对方不存在
        $this->expectBizCode(BizCode::USER_NOT_FOUND, fn () => $this->query->with($male, $female->id));
    }

    public function testWithRejectsSelf(): void
    {
        $male = $this->makeMale(balance: 100);

        $this->expectBizCode(BizCode::USER_NOT_FOUND, fn () => $this->query->with($male, $male->id));
    }

    // ---------- 历史消息 ----------

    public function testMessagesAreNewestFirstWithHasMore(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();
        for ($i = 1; $i <= 5; ++$i) {
            $this->send($male, $female, "第 {$i} 条");
        }
        $conversationId = $this->conversationIdOf($male, $female);

        $first = $this->query->messages($female, $conversationId, null, 3);
        self::assertTrue($first['has_more']);
        self::assertCount(3, $first['list']);
        self::assertSame(['第 5 条', '第 4 条', '第 3 条'], array_column($first['list'], 'content'));

        // 上拉：拿这一页里最老的那条的 id 当 before_id
        $beforeId = $first['list'][2]['id'];
        $second = $this->query->messages($female, $conversationId, $beforeId, 3);
        self::assertFalse($second['has_more']);
        self::assertSame(['第 2 条', '第 1 条'], array_column($second['list'], 'content'));
    }

    public function testMessagesRejectsForeignConversation(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $stranger = $this->makeFemale();
        $this->send($male, $female, '你好');

        $this->expectBizCode(
            BizCode::USER_NOT_FOUND,
            fn () => $this->query->messages($stranger, $this->conversationIdOf($male, $female), null, 30),
        );
    }

    // ---------- 增量补拉 ----------

    public function testSyncReturnsOnlyReceivedMessagesAfterCursor(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();
        $this->send($male, $female, '一');
        $cursor = (int) Message::query()->max('id');
        $this->send($male, $female, '二');
        $this->send($female, $male, '我发的');

        $result = $this->query->sync($female, $cursor, 100);

        self::assertCount(1, $result['list'], '自己发的不用补拉，本地已经有了');
        self::assertSame('二', $result['list'][0]['content']);
        self::assertSame((int) $result['list'][0]['id'], $result['max_id']);
        self::assertFalse($result['has_more']);
        self::assertSame(2, $result['unread']['conversation'], '补拉顺便把未读带回来，少一次请求');
    }

    public function testSyncAdvancesCursorOnlyToWhatItReturned(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();
        for ($i = 1; $i <= 3; ++$i) {
            $this->send($male, $female, "第 {$i} 条");
        }

        $first = $this->query->sync($female, 0, 2);
        self::assertTrue($first['has_more']);
        self::assertCount(2, $first['list']);
        self::assertSame(['第 1 条', '第 2 条'], array_column($first['list'], 'content'));

        $second = $this->query->sync($female, $first['max_id'], 2);
        self::assertFalse($second['has_more']);
        self::assertSame(['第 3 条'], array_column($second['list'], 'content'));

        // 游标推到头之后再拉就没有了
        $third = $this->query->sync($female, $second['max_id'], 2);
        self::assertSame([], $third['list']);
        self::assertSame($second['max_id'], $third['max_id'], '没东西可拉时游标不动');
    }

    // ---------- 收费说明 ----------

    public function testFeeRulesFollowConfigForMale(): void
    {
        $male = $this->makeMale();

        $items = make(MessagePricingService::class)->feeRuleItems($male);

        self::assertContains('每对用户之间，前 3 条私信免费', $items);
        self::assertContains('互相关注后，私信免费', $items);
        self::assertContains('会员每天额外 10 条免费私信（不限对象）', $items);
        self::assertContains('私信按对方等级计价：Lv1 5 币/条、Lv2 8 币/条、Lv3 10 币/条、Lv4 15 币/条、Lv5 20 币/条', $items);
        self::assertContains('充值比例：1 元 = 10 金币，余额不足时无法发送', $items);

        // 后台把免费条数改成 0，第一句要跟着变（客户明确要求「第一条就收费」可配）
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        self::assertContains('每对用户之间不设免费条数，第 1 条即计费', make(MessagePricingService::class)->feeRuleItems($male));
    }

    public function testFeeRulesDescribeShareForFemale(): void
    {
        $female = $this->makeFemale(level: 3);

        $items = make(MessagePricingService::class)->feeRuleItems($female);

        self::assertContains('女用户发送私信免费，不扣金币', $items);
        self::assertContains('收到男用户的付费私信，按你的等级分成：Lv1 30%、Lv2 35%、Lv3 40%、Lv4 45%、Lv5 50%', $items);
        self::assertContains('未完成实名认证时，分成按 50% 计算', $items);
        self::assertContains('分成以积分结算，100 积分 = 1 元，可在「我的收益」提现', $items);
        // 女方不该看到「怎么扣钱」
        foreach ($items as $item) {
            self::assertStringNotContainsString('币/条', $item);
        }
    }

    // ---------- 工具 ----------

    private function send(User $sender, User $receiver, string $content): array
    {
        return $this->billing->send($sender, $receiver->id, Message::TYPE_TEXT, $content, 'c-' . (++$this->sequence));
    }

    private function conversationIdOf(User $a, User $b): int
    {
        [$low, $high] = Conversation::pair($a->id, $b->id);

        return (int) Conversation::query()
            ->where('user_low_id', $low)
            ->where('user_high_id', $high)
            ->value('id');
    }

    private function expectBizCode(BizCode $code, callable $action): void
    {
        try {
            $action();
            self::fail('应该抛业务错误 ' . $code->value);
        } catch (ClBusinessException $e) {
            self::assertSame($code->value, $e->getResponse()->code);
        }
    }

    private function makeMale(int $balance = 0, bool $vip = false): User
    {
        $index = ++$this->sequence;
        $male = User::query()->create([
            'account' => 'male' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '男' . $index,
            'avatar' => 'https://cdn.example.com/m' . $index . '.png',
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
            'vip_expire_at' => $vip ? Carbon::now()->addDays(30)->toDateTimeString() : null,
        ]);

        make(CoinWalletService::class)->createForUser($male->id);
        if ($balance > 0) {
            make(CoinWalletService::class)->increase(
                $male->id,
                $balance,
                CoinLogType::RECHARGE,
                'test',
                $male->id,
                '测试充值',
            );
        }

        return $male->refresh();
    }

    private function makeFemale(int $level = 1, bool $realNamePassed = true): User
    {
        $index = ++$this->sequence;
        $female = User::query()->create([
            'account' => 'female' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女' . $index,
            'avatar' => 'https://cdn.example.com/f' . $index . '.png',
            'level' => $level,
            'real_name_status' => $realNamePassed ? User::REAL_NAME_PASSED : User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);

        return $female->refresh();
    }

    /** 等级配置按 docs/06 第 2.3 节的默认值来（和 MessageBillingTest 保持一致）。 */
    private function seedLevels(): void
    {
        $repository = make(LevelConfigRepository::class);
        $rows = [
            1 => [0, 5, 3000],
            2 => [20000, 8, 3500],
            3 => [100000, 10, 4000],
            4 => [300000, 15, 4500],
            5 => [1000000, 20, 5000],
        ];

        foreach ($rows as $level => [$threshold, $price, $rate]) {
            $existing = $repository->findByLevel($level);
            $attributes = [
                'name' => 'Lv' . $level,
                'threshold_points' => $threshold,
                'message_price' => $price,
                'message_share_rate' => $rate,
                'voice_price' => 60 * $level,
                'video_price' => 120 * $level,
            ];

            if ($existing instanceof LevelConfig) {
                $existing->update($attributes);
            } else {
                LevelConfig::query()->create(array_merge(['level' => $level], $attributes));
            }
        }
    }

    private function resetConfig(): void
    {
        $config = make(AppConfigService::class);
        $config->set('chat.free_count_per_pair', 3);
        $config->set('chat.mutual_follow_free', 1);
        $config->set('chat.message_max_length', 500);
        $config->set('chat.image_same_price', 1);
        $config->set('vip.daily_free_messages', 10);
        $config->set('share.unverified_factor', 5000);
        $config->set('point.per_yuan', 100);
        $config->set('coin.per_yuan', 10);
    }

    private function clearAll(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();
        Visit::query()->delete();
        SensitiveWord::query()->delete();
        Follow::query()->delete();
        Block::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();

        $redis = make(Redis::class);
        $keys = $redis->keys('vip_free:*');
        if (! empty($keys)) {
            $redis->del(...$keys);
        }
        make(SensitiveWordService::class)->flush();
    }
}
