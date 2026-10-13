<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Gift;

use App\Exception\ClBusinessException;
use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Follow;
use App\Model\Cl\Gift;
use App\Model\Cl\GiftRecord;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Message;
use App\Model\Cl\PlatformIncome;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\SensitiveWord;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Gift\GiftBillingService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 送礼物（docs/06 第 4 节 + 第 10 节场景 14~16）。
 *
 * 场景 14 的数字是文档里写死的：120 金币的礼物 → 女方 +floor(120×3400×100/(10000×10)) = **408 积分**。
 *
 * @internal
 * @coversNothing
 */
final class GiftBillingTest extends TestCase
{
    use RunTestsInCoroutine;

    private GiftBillingService $billing;

    /** 种下去的 6 个礼物 id（1 基：礼物 4 = 浪漫花束 120 金币）。 */
    private array $giftIds = [];

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->resetConfig();
        $this->seedLevels();
        $this->seedGifts();

        $this->billing = make(GiftBillingService::class);
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 场景 14：送 120 金币礼物 ----------

    public function testScene14GiftDeductsCoinsAndAddsPoints(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();

        $result = $this->send($male, $female, giftId: $this->giftId(4));

        // 男方 -120
        self::assertSame(380, $result['balance']);
        self::assertSame(380, make(CoinWalletService::class)->balanceOf($male->id));
        // 女方 +408（docs 场景 14 的原话）
        self::assertSame(408, make(PointWalletService::class)->summaryOf($female->id)['balance']);
        self::assertSame(408, (int) $female->refresh()->total_earned_points);

        // 赠送记录把「送出那一刻」的名字和价格存下来
        $record = GiftRecord::query()->firstOrFail();
        self::assertSame('浪漫花束', $record->gift_name);
        self::assertSame(120, $record->price_coins);
        self::assertSame(3400, $record->share_rate);
        self::assertSame(408, $record->receiver_points);
        self::assertNotNull($record->message_id);

        // 金币流水 + 积分流水 + 平台收入各一条
        self::assertSame(1, $this->coinLogCount($male->id, 'GIFT'));
        self::assertSame(1, $this->pointLogCount($female->id, 'GIFT_INCOME'));
        $income = PlatformIncome::query()->firstOrFail();
        self::assertSame('GIFT', $income->source);
        self::assertSame(120, (int) $income->coins);
        self::assertSame(408, (int) $income->receiver_points);
    }

    // ---------- 场景 15：余额不足 ----------

    public function testScene15NotEnoughCoinsChangesNothing(): void
    {
        $male = $this->makeMale(balance: 100);
        $female = $this->makeFemale();

        $this->expectBizCode(BizCode::COIN_NOT_ENOUGH, fn () => $this->send($male, $female, giftId: $this->giftId(4)));

        self::assertSame(100, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(0, make(PointWalletService::class)->summaryOf($female->id)['balance']);
        self::assertSame(0, GiftRecord::query()->count(), '事务回滚，赠送记录不该留下');
        self::assertSame(0, Message::query()->count(), '礼物消息也不该留下');
        self::assertSame(0, PlatformIncome::query()->count());
    }

    // ---------- 场景 16：礼物不计入 male_sent_count ----------

    public function testScene16GiftMessageDoesNotCountAsSentMessage(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();

        $this->send($male, $female, giftId: $this->giftId(1));

        $conversation = Conversation::query()->firstOrFail();
        self::assertSame(0, (int) $conversation->male_sent_count, '礼物不算「已发送的普通消息」');

        // 反证：普通消息才会 +1
        make(\App\Service\Cl\Chat\MessageBillingService::class)->send($male, $female->id, Message::TYPE_TEXT, '你好', 'c-text-1');
        self::assertSame(1, (int) $conversation->refresh()->male_sent_count);
    }

    // ---------- 幂等 ----------

    public function testSameOrderIdOnlyChargesOnce(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();

        $first = $this->billing->send($male, $female->id, $this->giftId(4), 'order-1');
        $second = $this->billing->send($male, $female->id, $this->giftId(4), 'order-1');

        self::assertSame($first['message']['id'], $second['message']['id'], '返回的还是同一条礼物消息');
        self::assertSame(380, $second['balance'], '第二次不该再扣');
        self::assertSame(1, GiftRecord::query()->count());
        self::assertSame(1, Message::query()->count());
        self::assertSame(408, make(PointWalletService::class)->summaryOf($female->id)['balance'], '积分也只加一次');
    }

    // ---------- 校验 ----------

    public function testFemaleCannotSendGift(): void
    {
        $female = $this->makeFemale();
        $male = $this->makeMale(balance: 500);

        $this->expectBizCode(BizCode::STATUS_NOT_ALLOWED, fn () => $this->send($female, $male, giftId: $this->giftId(1)));
        self::assertSame(0, GiftRecord::query()->count());
    }

    public function testGiftToSameGenderIsRejected(): void
    {
        $male = $this->makeMale(balance: 500);
        $otherMale = $this->makeMale(balance: 0);

        $this->expectBizCode(BizCode::SAME_GENDER_NOT_ALLOWED, fn () => $this->send($male, $otherMale, giftId: $this->giftId(1)));
    }

    public function testGiftToSelfIsRejected(): void
    {
        $male = $this->makeMale(balance: 500);

        $this->expectBizCode(BizCode::SAME_GENDER_NOT_ALLOWED, fn () => $this->send($male, $male, giftId: $this->giftId(1)));
    }

    public function testBlockedUserCannotSendGift(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();
        make(BlockRepository::class)->add($female->id, $male->id);

        $this->expectBizCode(BizCode::BLOCKED_BY_TARGET, fn () => $this->send($male, $female, giftId: $this->giftId(1)));
        self::assertSame(500, make(CoinWalletService::class)->balanceOf($male->id));
    }

    public function testDisabledGiftChangesNothing(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();
        Gift::query()->whereKey($this->giftId(1))->update(['enabled' => 0]);

        $this->expectBizCode(BizCode::STATUS_NOT_ALLOWED, fn () => $this->send($male, $female, giftId: $this->giftId(1)));

        self::assertSame(500, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(0, GiftRecord::query()->count());
    }

    public function testUnknownGiftIsRejected(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();

        $this->expectBizCode(BizCode::STATUS_NOT_ALLOWED, fn () => $this->send($male, $female, giftId: 9999));
    }

    // ---------- 分成口径 ----------

    /**
     * 礼物分成**不随等级变化**，而且**默认不套未实名系数**
     * （`share.unverified_apply_gift` 默认 0；私信那边是默认要套的）。
     */
    public function testUnverifiedFactorDoesNotApplyToGiftByDefault(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale(level: 3, realNamePassed: false);

        $this->send($male, $female, giftId: $this->giftId(4));

        self::assertSame(408, make(PointWalletService::class)->summaryOf($female->id)['balance'], '未实名也不打折');
        self::assertSame(3400, (int) GiftRecord::query()->firstOrFail()->share_rate);
    }

    public function testUnverifiedFactorAppliesWhenEnabled(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale(level: 3, realNamePassed: false);
        make(AppConfigService::class)->set('share.unverified_apply_gift', 1);

        $this->send($male, $female, giftId: $this->giftId(4));

        // 3400 × 5000 ÷ 10000 = 1700 → floor(120 × 1700 × 100 / 100000) = 204
        self::assertSame(1700, (int) GiftRecord::query()->firstOrFail()->share_rate);
        self::assertSame(204, make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    /** 礼物收入也算「累计收益」，跨过门槛要升级（docs/06 第 5 节）。 */
    public function testGiftPointsCanTriggerUpgrade(): void
    {
        $male = $this->makeMale(balance: 1000);
        $female = $this->makeFemale(level: 1);
        // 差一点点到 Lv2（20000）
        $female->total_earned_points = 20000 - 408;
        $female->save();

        $this->send($male, $female, giftId: $this->giftId(4));

        self::assertSame(2, (int) $female->refresh()->level, '累计收益跨过 20000 就升 Lv2');
    }

    /** 礼物消息要真的进会话：状态正常、类型是礼物、不被当成收费私信。 */
    public function testGiftMessageIsWrittenIntoConversation(): void
    {
        $male = $this->makeMale(balance: 500);
        $female = $this->makeFemale();

        $result = $this->send($male, $female, giftId: $this->giftId(4));

        self::assertSame('浪漫花束', $result['message']['content']);
        self::assertSame(Message::TYPE_GIFT, $result['message']['type']);
        self::assertSame(0, $result['message']['price_coins'], '礼物消息本身不按私信计费');

        $conversation = Conversation::query()->firstOrFail();
        self::assertSame((int) $result['message']['id'], (int) $conversation->last_message_id);
        self::assertSame(1, $conversation->unreadFor($female->id), '对方未读 +1');
    }

    // ---------- 工具 ----------

    /**
     * @return array{message: array<string,mixed>, balance: int}
     */
    private function send(User $sender, User $receiver, int $giftId): array
    {
        return $this->billing->send($sender, $receiver->id, $giftId, 'order-' . (++$this->sequence));
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

    private function coinLogCount(int $userId, string $type): int
    {
        return \App\Model\Cl\CoinLog::query()->where('user_id', $userId)->where('type', $type)->count();
    }

    private function pointLogCount(int $userId, string $type): int
    {
        return \App\Model\Cl\PointLog::query()->where('user_id', $userId)->where('type', $type)->count();
    }

    private function makeMale(int $balance = 0): User
    {
        $index = ++$this->sequence;
        $male = User::query()->create([
            'account' => 'gmale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '男' . $index,
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
        ]);

        make(CoinWalletService::class)->createForUser($male->id);
        if ($balance > 0) {
            make(CoinWalletService::class)->increase($male->id, $balance, CoinLogType::RECHARGE, 'test', $male->id, '测试充值');
        }

        return $male->refresh();
    }

    private function makeFemale(int $level = 1, bool $realNamePassed = true): User
    {
        $index = ++$this->sequence;
        $female = User::query()->create([
            'account' => 'gfemale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女' . $index,
            'level' => $level,
            'real_name_status' => $realNamePassed ? User::REAL_NAME_PASSED : User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);

        return $female->refresh();
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

    /** 第 n 个礼物（1 基）。 */
    private function giftId(int $n): int
    {
        return $this->giftIds[$n - 1];
    }

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
            $attributes = [
                'name' => 'Lv' . $level,
                'threshold_points' => $threshold,
                'message_price' => $price,
                'message_share_rate' => $rate,
                'voice_price' => 60 * $level,
                'video_price' => 120 * $level,
            ];
            $existing = $repository->findByLevel($level);
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
        $config->set('share.gift_rate', 3400);
        $config->set('share.unverified_apply_gift', 0);
        $config->set('share.unverified_factor', 5000);
        $config->set('point.per_yuan', 100);
        $config->set('coin.per_yuan', 10);
        $config->set('chat.free_count_per_pair', 3);
        $config->set('chat.mutual_follow_free', 1);
        $config->set('chat.message_max_length', 500);
        $config->set('vip.daily_free_messages', 10);
    }

    private function clearAll(): void
    {
        PlatformIncome::query()->delete();
        GiftRecord::query()->delete();
        Gift::query()->delete();
        \App\Model\Cl\CoinLog::query()->delete();
        \App\Model\Cl\PointLog::query()->delete();
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
    }
}
