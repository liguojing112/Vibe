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
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PointLogType;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Chat\MessageBillingService;
use App\Service\Cl\Chat\SensitiveWordService;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use function Hyperf\Coroutine\parallel;
use PHPUnit\Framework\TestCase;

/**
 * 私信计费（docs/06 第 3.3 节的七步 + 第 10 节场景 1~13）。
 *
 * 金额一律整数、只在服务端算，所以这里逐个场景把「谁扣了多少、谁加了多少、余额剩多少」都断言死。
 *
 * @internal
 * @coversNothing
 */
final class MessageBillingTest extends TestCase
{
    use RunTestsInCoroutine;

    private MessageBillingService $billing;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->resetConfig();
        $this->seedLevels();

        $this->billing = make(MessageBillingService::class);
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 场景 1：前 3 条免费，第 4 条扣 Lv1 单价 5，女方 +15 积分 ----------

    public function testScene01FirstThreeFreeThenChargeAndShare(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);

        for ($i = 1; $i <= 3; ++$i) {
            $result = $this->send($male, $female, "第 {$i} 条");
            self::assertSame(0, $result['message']['price_coins'], "第 {$i} 条应该免费");
            self::assertSame('PAIR_FREE', $result['message']['free_reason']);
        }

        // 前三条免费时，男方余额一点没动
        self::assertSame(100, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(0, make(PointWalletService::class)->summaryOf($female->id)['balance']);

        $fourth = $this->send($male, $female, '第 4 条');

        self::assertSame(5, $fourth['message']['price_coins']);
        self::assertNull($fourth['message']['free_reason']);
        self::assertSame(15, $fourth['message']['receiver_points']);
        self::assertSame(95, $fourth['balance']);
        self::assertSame(95, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(15, make(PointWalletService::class)->summaryOf($female->id)['balance']);

        // 会话里的已发条数只算普通消息
        self::assertSame(4, Conversation::query()->where('male_id', $male->id)->value('male_sent_count'));
    }

    // ---------- 场景 2：free_count_per_pair 改成 0，第 1 条就扣费 ----------

    public function testScene02ZeroFreeCountChargesFromTheFirstMessage(): void
    {
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);

        $result = $this->send($male, $female, '第一条就收费');

        self::assertSame(5, $result['message']['price_coins']);
        self::assertSame(95, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(15, make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    // ---------- 场景 3：互关免费；关掉开关后恢复收费 ----------

    public function testScene03MutualFollowIsFreeUntilSwitchedOff(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        // 对子免费条数先用完，这样第二轮才能看出「恢复收费」
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        $this->followBoth($male, $female);

        $free = $this->send($male, $female, '互关免费');
        self::assertSame(0, $free['message']['price_coins']);
        self::assertSame('MUTUAL_FOLLOW', $free['message']['free_reason']);
        self::assertSame(100, make(CoinWalletService::class)->balanceOf($male->id));

        make(AppConfigService::class)->set('chat.mutual_follow_free', 0);

        $charged = $this->send($male, $female, '关掉开关就得付钱');
        self::assertSame(5, $charged['message']['price_coins']);
        self::assertSame(95, make(CoinWalletService::class)->balanceOf($male->id));
    }

    // ---------- 场景 4：会员每天 10 条免费，第 11 条收费；第二天重新计数 ----------

    public function testScene04VipDailyFreeThenChargedAndResetsNextDay(): void
    {
        $male = $this->makeMale(balance: 100, vip: true);
        $female = $this->makeFemale(level: 1);
        // 把对子免费用完，直接测会员额度
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        for ($i = 1; $i <= 10; ++$i) {
            $result = $this->send($male, $female, "会员第 {$i} 条");
            self::assertSame(0, $result['message']['price_coins'], "会员第 {$i} 条应该免费");
            self::assertSame('VIP_FREE', $result['message']['free_reason']);
        }

        $eleventh = $this->send($male, $female, '第 11 条');
        self::assertSame(5, $eleventh['message']['price_coins']);
        self::assertSame(95, make(CoinWalletService::class)->balanceOf($male->id));

        // 「第二天重新计数」：把额度计数挪到昨天，今天就是满的
        $redis = make(Redis::class);
        $key = 'vip_free:' . $male->id . ':' . Carbon::now()->format('Ymd');
        $redis->del($key);

        $nextDay = $this->send($male, $female, '第二天');
        self::assertSame(0, $nextDay['message']['price_coins'], '第二天额度应该重置');
    }

    // ---------- 场景 5：判断顺序（互关/对子免费时不消耗会员额度） ----------

    public function testScene05OrderOfRulesDoesNotConsumeVipQuota(): void
    {
        $male = $this->makeMale(balance: 100, vip: true);
        $female = $this->makeFemale(level: 1);
        $this->followBoth($male, $female);

        $this->send($male, $female, '互关免费');
        self::assertSame(0, make(\App\Service\Cl\Pricing\VipFreeQuota::class)->usedToday($male->id), '互关免费不该动会员额度');

        // 取消互关，靠「对子免费」的三条，也不该动会员额度
        Follow::query()->delete();
        $this->send($male, $female, '对子免费');
        self::assertSame(0, make(\App\Service\Cl\Pricing\VipFreeQuota::class)->usedToday($male->id), '对子免费不该动会员额度');

        // 对子用完（free_count_per_pair=3 已发满 3 条）后再发，才轮到会员额度
        $this->send($male, $female, '第三条对子免费');
        $this->send($male, $female, '这次才用会员额度');

        self::assertSame(1, make(\App\Service\Cl\Pricing\VipFreeQuota::class)->usedToday($male->id));
    }

    // ---------- 场景 6：余额不足 ----------

    public function testScene06NotEnoughCoinsChangesNothing(): void
    {
        $male = $this->makeMale(balance: 4);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        $this->expectBizCode(BizCode::COIN_NOT_ENOUGH, fn () => $this->send($male, $female, '钱不够'));

        self::assertSame(4, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(0, Message::query()->count(), '消息不该落库');
        self::assertSame(0, make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    // ---------- 场景 7：同一 client_msg_id 只扣一次 ----------

    public function testScene07SameClientMsgIdIsChargedOnce(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        $first = $this->send($male, $female, '你好', 'same-client-id');
        $second = $this->send($male, $female, '你好', 'same-client-id');

        self::assertSame($first['message']['id'], $second['message']['id']);
        self::assertSame(1, Message::query()->count());
        self::assertSame(95, make(CoinWalletService::class)->balanceOf($male->id), '只该扣一次');
    }

    // ---------- 场景 8：并发发消息，余额绝不出现负数 ----------

    public function testScene08ConcurrentSendsNeverGoNegative(): void
    {
        $male = $this->makeMale(balance: 10);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        $results = parallel([
            fn () => $this->trySend($male, $female, '并发 1'),
            fn () => $this->trySend($male, $female, '并发 2'),
            fn () => $this->trySend($male, $female, '并发 3'),
        ]);

        $succeeded = count(array_filter($results, static fn ($r) => $r === true));

        self::assertSame(2, $succeeded, '10 金币、单价 5，只能成功 2 条');
        self::assertSame(0, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(2, Message::query()->count());
    }

    /**
     * 补一个**真能区分「有没有锁」**的并发用例。
     *
     * 场景 8 那种（余额 10、单价 5、发 3 条）串行跑也是「成功 2 条」，证明不了什么。
     * 这里只给 1 条免费额度再并发发 3 条：**没有行锁的话**三条会同时读到「还没发过」全都免单；
     * 有锁则第二条必须等第一条提交、读到 count=1 → 乖乖收费。
     */
    public function testConcurrentSendsOnlyOneGetsTheFreeSlot(): void
    {
        make(AppConfigService::class)->set('chat.free_count_per_pair', 1);
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);

        parallel([
            fn () => $this->trySend($male, $female, '并发免费 1'),
            fn () => $this->trySend($male, $female, '并发免费 2'),
            fn () => $this->trySend($male, $female, '并发免费 3'),
        ]);

        self::assertSame(3, Message::query()->count());
        self::assertSame(
            1,
            Message::query()->where('price_coins', 0)->count(),
            '只允许 1 条免费，并发下也只能有 1 条免单',
        );
        self::assertSame(90, make(CoinWalletService::class)->balanceOf($male->id), '另外两条按 Lv1 单价各扣 5');
    }

    // ---------- 场景 9：女 → 男免费、不给积分 ----------
    public function testScene09FemaleToMaleIsFreeAndGivesNoPoints(): void
    {
        $female = $this->makeFemale(level: 1);
        $male = $this->makeMale(balance: 100);

        $result = $this->send($female, $male, '你好呀');

        self::assertSame(0, $result['message']['price_coins']);
        self::assertSame('FEMALE_SEND', $result['message']['free_reason']);
        self::assertSame(0, $result['message']['receiver_points']);
        self::assertNull($result['fee'], '女用户没有收费信息');
        self::assertSame(100, make(CoinWalletService::class)->balanceOf($male->id), '男方一分不扣');
        self::assertSame(0, make(CoinWalletService::class)->balanceOf($female->id), '女方也没有金币进账');
        self::assertSame(0, Conversation::query()->value('male_sent_count'), '女发男不计入 male_sent_count');
    }

    // ---------- 场景 10：男 → 男 ----------

    public function testScene10MaleToMaleIsRejected(): void
    {
        $male = $this->makeMale(balance: 100);
        $otherMale = $this->makeMale(balance: 0);

        $this->expectBizCode(BizCode::SAME_GENDER_NOT_ALLOWED, fn () => $this->send($male, $otherMale, '哥们'));

        self::assertSame(0, Message::query()->count());
    }

    // ---------- 场景 11：未实名女方分成打五折 ----------

    public function testScene11UnverifiedFemaleShareIsHalved(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1, realNamePassed: false);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        $result = $this->send($male, $female, '还没实名');

        // Lv1 分成 3000 → ×5000/10000 = 1500 → floor(5 × 1500 × 100 ÷ 100000) = 7
        self::assertSame(7, $result['message']['receiver_points']);
        // 分成比例不往客户端暴露，直接看库里的值
        self::assertSame(1500, (int) Message::query()->orderByDesc('id')->value('share_rate'));
        self::assertSame(7, make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    // ---------- 场景 12：Lv3 单价 10、分成 4000 → 40 积分 ----------

    public function testScene12LevelThreeGivesFortyPoints(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 3);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        $result = $this->send($male, $female, '你好');

        self::assertSame(10, $result['message']['price_coins']);
        self::assertSame(40, $result['message']['receiver_points']);
        self::assertSame(3, (int) Message::query()->orderByDesc('id')->value('receiver_level'));
        self::assertSame(40, make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    // ---------- 场景 13：被拉黑后发送失败且不扣费 ----------

    public function testScene13BlockedSenderCannotSendAndIsNotCharged(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        Block::query()->create(['user_id' => $female->id, 'target_id' => $male->id]);

        $this->expectBizCode(BizCode::BLOCKED_BY_TARGET, fn () => $this->send($male, $female, '在吗'));

        self::assertSame(100, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(0, Message::query()->count());
    }

    // ---------- 敏感词：只标记不拦截 ----------

    public function testSensitiveWordIsMarkedButStillDelivered(): void
    {
        make(SensitiveWordService::class)->flush();
        SensitiveWord::query()->create(['word' => '测试敏感词']);

        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);

        $result = $this->send($male, $female, '这里有测试敏感词');

        self::assertSame(1, $result['message']['sensitive_hit']);
        self::assertSame(1, Message::query()->count(), '命中敏感词也照常送达');

        // 命中的消息要进后台待审队列（`04` 第 3 节「只标记不拦截」）。
        // 这两个字段**必须一起写**：只写 sensitive_hit 的话，后台审核中心那一页永远是空的。
        self::assertSame(
            Message::REVIEW_PENDING,
            (int) Message::query()->firstOrFail()->review_status,
            '命中的消息要进待审队列',
        );

        // 正常内容不打标
        $clean = $this->send($male, $female, '很干净的内容');
        self::assertSame(0, $clean['message']['sensitive_hit']);
        self::assertSame(
            Message::REVIEW_NONE,
            (int) Message::query()->orderByDesc('id')->firstOrFail()->review_status,
            '没命中的不用审',
        );
    }

    // ---------- 场景 17~19：升级 ----------

    public function testScene17CrossingThresholdUpgradesLevel(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        User::query()->whereKey($female->id)->update(['total_earned_points' => 19990]);
        $female->refresh();

        $this->send($male, $female, '这一条就跨过门槛');

        self::assertSame(2, User::query()->whereKey($female->id)->value('level'), '19990 + 15 = 20005 → 升 Lv2');
    }

    public function testScene18CanJumpSeveralLevelsAtOnce(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        // 399990 + 15 会一次跨过 20000 与 100000 两档门槛，也应该直接到「满足的最高级」
        User::query()->whereKey($female->id)->update(['total_earned_points' => 399990]);
        $female->refresh();

        $this->send($male, $female, '一次跨两级');

        self::assertSame(4, User::query()->whereKey($female->id)->value('level'));
    }

    public function testScene19LockedLevelDoesNotUpgrade(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale(level: 1);
        make(AppConfigService::class)->set('chat.free_count_per_pair', 0);
        User::query()->whereKey($female->id)->update([
            'total_earned_points' => 999999,
            'level_locked' => 1,
        ]);
        $female->refresh();

        $this->send($male, $female, '锁了就不升');

        self::assertSame(1, User::query()->whereKey($female->id)->value('level'));
    }

    // ---------- 工具 ----------

    private function send(User $sender, User $receiver, string $content, ?string $clientMsgId = null): array
    {
        $clientMsgId ??= 'c-' . (++$this->sequence);

        return $this->billing->send($sender, $receiver->id, Message::TYPE_TEXT, $content, $clientMsgId);
    }

    /**
     * 并发场景用：成功返回 true，业务错误（余额不足）返回 false。
     */
    private function trySend(User $sender, User $receiver, string $content): bool
    {
        try {
            $this->send($sender, $receiver, $content);

            return true;
        } catch (ClBusinessException $e) {
            return $e->getResponse()->code === BizCode::COIN_NOT_ENOUGH->value ? false : throw $e;
        }
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

    private function followBoth(User $a, User $b): void
    {
        Follow::query()->create(['user_id' => $a->id, 'target_id' => $b->id]);
        Follow::query()->create(['user_id' => $b->id, 'target_id' => $a->id]);
    }

    /** 等级配置按 docs/06 第 2.3 节的默认值来（工程里本来就有种子，这里保证测试库也有）。 */
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
        $config->set('vip.daily_free_messages', 10);
        $config->set('share.unverified_factor', 5000);
        $config->set('point.per_yuan', 100);
        $config->set('coin.per_yuan', 10);
    }

    private function clearAll(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();
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
