<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Pricing;

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
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Pricing\MessageQuote;
use App\Service\Cl\Pricing\VipFreeQuota;
use Hyperf\Carbon\Carbon;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 私信报价（docs/06 第 3.2 节）——覆盖第 10 节场景 1~5 里 quote 的判断。
 *
 * @internal
 * @coversNothing
 */
final class MessagePricingServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private MessagePricingService $pricing;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sequence = 0;
        $this->clearAll();
        $this->clearVipQuota();

        // 场景默认值（docs/06 第 2 节）
        $this->setConfig('chat.free_count_per_pair', 3);
        $this->setConfig('chat.mutual_follow_free', 1);
        $this->setConfig('vip.daily_free_messages', 10);

        $this->pricing = make(MessagePricingService::class);
    }

    protected function tearDown(): void
    {
        $this->setConfig('chat.free_count_per_pair', 3);
        $this->setConfig('chat.mutual_follow_free', 1);
        $this->setConfig('vip.daily_free_messages', 10);
        $this->clearVipQuota();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 场景 1 ----------

    public function testFirstThreeMessagesOfAPairAreFree(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);

        foreach ([0, 1, 2] as $sent) {
            $quote = $this->pricing->quote($male, $female, $sent);
            self::assertTrue($quote->isFree(), "已发 {$sent} 条时应该免费");
            self::assertSame(MessageQuote::FREE_PAIR, $quote->freeReason);
            self::assertSame(0, $quote->price);
        }
        // 还剩几条也要对：已发 0 条 → 还能免费 3 条
        self::assertSame(3, $this->pricing->quote($male, $female, 0)->pairFreeRemaining);
        self::assertSame(1, $this->pricing->quote($male, $female, 2)->pairFreeRemaining);
    }

    public function testFourthMessageIsChargedWithLevelOnePrice(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE, ['level' => 1]);
        $level = $this->levelConfig(1, 5);

        $quote = $this->pricing->quote($male, $female, 3);

        self::assertFalse($quote->isFree());
        self::assertNull($quote->freeReason);
        self::assertSame((int) $level->message_price, $quote->price);
    }

    // ---------- 场景 2 ----------

    public function testZeroFreeCountChargesTheVeryFirstMessage(): void
    {
        $this->setConfig('chat.free_count_per_pair', 0);
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);

        $quote = $this->pricing->quote($male, $female, 0);

        self::assertFalse($quote->isFree());
        self::assertSame(5, $quote->price);
    }

    // ---------- 场景 3 ----------

    public function testMutualFollowIsFreeAndCanBeSwitchedOff(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);
        // 对子免费条数已用完，这样第二轮才能看出"恢复收费"
        $this->followBoth($male, $female);

        $quote = $this->pricing->quote($male, $female, 3);
        self::assertTrue($quote->isFree());
        self::assertSame(MessageQuote::FREE_MUTUAL_FOLLOW, $quote->freeReason);

        $this->setConfig('chat.mutual_follow_free', 0);

        $again = $this->pricing->quote($male, $female, 3);
        self::assertFalse($again->isFree());
        self::assertSame(5, $again->price);
    }

    public function testOneWayFollowIsNotEnough(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);
        Follow::query()->create(['user_id' => $male->id, 'target_id' => $female->id]);

        // 单向关注不算互关，对子条数也用完了 → 收费
        $quote = $this->pricing->quote($male, $female, 3);

        self::assertFalse($quote->isFree());
    }

    // ---------- 场景 4 ----------

    public function testVipGetsTenFreeMessagesPerDay(): void
    {
        $male = $this->makeVip();
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);

        // 已用 0~9 条：免费
        foreach ([0, 5, 9] as $used) {
            $this->setVipUsed($male->id, $used);
            $quote = $this->pricing->quote($male, $female, 3);
            self::assertTrue($quote->isFree(), "今天已用 {$used} 条时应该还有额度");
            self::assertSame(MessageQuote::FREE_VIP, $quote->freeReason);
        }

        // 第 11 条（已用 10 条）开始收费
        $this->setVipUsed($male->id, 10);
        $quote = $this->pricing->quote($male, $female, 3);
        self::assertFalse($quote->isFree());
        self::assertSame(5, $quote->price);
    }

    public function testVipQuotaResetsOnTheNextDay(): void
    {
        $male = $this->makeVip();
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);

        // 昨天已经把 10 条用光
        $redis = make(Redis::class);
        $redis->set('vip_free:' . $male->id . ':' . Carbon::now()->subDay()->format('Ymd'), 10);

        $quote = $this->pricing->quote($male, $female, 3);

        self::assertTrue($quote->isFree(), '今天应该重新计数');
        self::assertSame(MessageQuote::FREE_VIP, $quote->freeReason);
        self::assertSame(10, $quote->vipFreeRemaining);
    }

    // ---------- 场景 5：判断顺序 ----------

    public function testMutualFollowDoesNotConsumeVipQuota(): void
    {
        $male = $this->makeVip();
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->followBoth($male, $female);
        $this->setVipUsed($male->id, 2);

        $quote = $this->pricing->quote($male, $female, 0);

        // 命中第 1 步就停：理由必须是互关，而不是会员
        self::assertSame(MessageQuote::FREE_MUTUAL_FOLLOW, $quote->freeReason);
        self::assertSame(2, make(VipFreeQuota::class)->usedToday($male->id), 'quote 不该动会员额度');
    }

    public function testPairFreeDoesNotConsumeVipQuota(): void
    {
        $male = $this->makeVip();
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->setVipUsed($male->id, 2);

        $quote = $this->pricing->quote($male, $female, 1);

        // 对子免费条数没用完，也不该动会员额度
        self::assertSame(MessageQuote::FREE_PAIR, $quote->freeReason);
        self::assertSame(2, make(VipFreeQuota::class)->usedToday($male->id));
    }

    public function testNonVipHasNoVipQuota(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);

        $quote = $this->pricing->quote($male, $female, 3);

        self::assertFalse($quote->isFree());
        self::assertSame(0, $quote->vipFreeRemaining);
    }

    public function testExpiredVipHasNoQuota(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, [
            'vip_expire_at' => Carbon::now()->subDay()->toDateTimeString(),
        ]);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);

        self::assertFalse($this->pricing->quote($male, $female, 3)->isFree());
    }

    // ---------- 价格取女方等级 ----------

    public function testPriceFollowsFemaleLevel(): void
    {
        foreach ([1 => 5, 3 => 10, 5 => 20] as $levelNumber => $expectedPrice) {
            $male = $this->makeUser(User::GENDER_MALE);
            $female = $this->makeUser(User::GENDER_FEMALE, ['level' => $levelNumber]);
            $this->levelConfig($levelNumber, $expectedPrice);

            $quote = $this->pricing->quote($male, $female, 99);

            self::assertSame($expectedPrice, $quote->price, "Lv{$levelNumber} 单价应为 {$expectedPrice}");
            self::assertSame($levelNumber, $quote->receiverLevel);
        }
    }

    // ---------- 免费提示文案 ----------

    public function testFreeHints(): void
    {
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $this->levelConfig(1, 5);

        self::assertSame(
            '还可免费发送 3 条',
            $this->pricing->freeHint($this->pricing->quote($male, $female, 0)),
        );

        $vip = $this->makeVip();
        $this->setVipUsed($vip->id, 7);
        self::assertSame(
            '会员今日还剩 3 条免费',
            $this->pricing->freeHint($this->pricing->quote($vip, $female, 99)),
        );

        $this->followBoth($male, $female);
        self::assertSame(
            '互相关注，私信免费',
            $this->pricing->freeHint($this->pricing->quote($male, $female, 99)),
        );

        // 收费时不给提示（页面直接显示单价）—— 换一个跟女方没关系的男用户
        $stranger = $this->makeUser(User::GENDER_MALE);
        self::assertNull($this->pricing->freeHint($this->pricing->quote($stranger, $female, 3)));
    }

    // ---------- 工具 ----------

    /**
     * @param array<string,mixed> $attributes
     */
    private function makeUser(int $gender, array $attributes = []): User
    {
        $index = ++$this->sequence;

        return User::query()->create(array_merge([
            'account' => 'p' . $index . '_' . $gender,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => '用户' . $index,
            'avatar' => 'https://cdn.example.com/' . $index . '.png',
            'level' => 1,
            'status' => User::STATUS_NORMAL,
        ], $attributes));
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function makeVip(array $attributes = []): User
    {
        return $this->makeUser(User::GENDER_MALE, array_merge([
            'vip_expire_at' => Carbon::now()->addDays(30)->toDateTimeString(),
        ], $attributes));
    }

    private function followBoth(User $a, User $b): void
    {
        Follow::query()->create(['user_id' => $a->id, 'target_id' => $b->id]);
        Follow::query()->create(['user_id' => $b->id, 'target_id' => $a->id]);
    }

    private function levelConfig(int $level, int $messagePrice): LevelConfig
    {
        $existing = LevelConfig::query()->where('level', $level)->first();
        if ($existing instanceof LevelConfig) {
            $existing->update(['message_price' => $messagePrice]);

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

    private function setConfig(string $key, mixed $value): void
    {
        make(AppConfigService::class)->set($key, $value);
    }

    private function setVipUsed(int $userId, int $count): void
    {
        make(Redis::class)->set('vip_free:' . $userId . ':' . Carbon::now()->format('Ymd'), $count);
    }

    private function clearVipQuota(): void
    {
        $redis = make(Redis::class);
        $keys = $redis->keys('vip_free:*');
        if (! empty($keys)) {
            $redis->del(...$keys);
        }
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
