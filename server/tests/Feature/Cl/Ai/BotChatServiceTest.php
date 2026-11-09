<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Ai;

use App\Model\Cl\AiReplyLog;
use App\Model\Cl\BotChatPair;
use App\Model\Cl\BotChatRule;
use App\Model\Cl\BotChatTarget;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Message;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\SensitiveWord;
use App\Model\Cl\User;
use App\Repository\Cl\LevelConfigRepository;
use App\Service\Cl\Ai\BotChatService;
use App\Service\Cl\Ai\DeepSeekClient;
use App\Service\Cl\Chat\SensitiveWordService;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\PointWalletService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * AI 男女互聊（机器人男号 → 指定女号）。
 *
 * 这里的断言全是**不能被改坏的性质**：
 * - 男号发消息免费：`price_coins = 0`、`receiver_points = 0`；
 * - 女号的虚拟收益只计数：钱包、积分流水、累计收益一律不动；
 * - 轮数、间隔、时段、每日上限任一条挡住就不再发；
 * - 女号没开 AI 代聊时不产生任何回复。
 *
 * 模型调用用假的 `ClientFactory` 顶掉，测试不碰外网。
 *
 * @internal
 * @coversNothing
 */
final class BotChatServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private BotChatService $service;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->seedConfig();
        $this->seedLevels();
        $this->bindFakeAiClient();

        $this->service = make(BotChatService::class);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testMaleBotMessageIsFreeAndOnlyCountsVirtualPoints(): void
    {
        $rule = $this->makeRule();
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        self::assertTrue($this->tick($rule, $target, [$male]));

        $message = Message::query()->orderByDesc('id')->first();
        self::assertInstanceOf(Message::class, $message);
        self::assertSame(0, (int) $message->price_coins, '男号发消息不能扣金币');
        self::assertSame(0, (int) $message->receiver_points, '虚拟收益不能写进消息的结算字段');
        self::assertSame(BotChatService::FREE_REASON_BOT_MALE, $message->free_reason);
        self::assertSame($male->id, (int) $message->sender_id);
        self::assertSame($female->id, (int) $message->receiver_id);

        // Lv1 已实名：5 金币 × 3000 分成 × 100 积分/元 ÷ (10000 × 10 金币/元) = 15 积分
        $target->refresh();
        self::assertSame(15, (int) $target->virtual_points_today);
        self::assertSame(1, (int) $target->rounds_today);

        $pair = BotChatPair::query()->where('rule_id', $rule->id)->first();
        self::assertInstanceOf(BotChatPair::class, $pair);
        self::assertSame(1, (int) $pair->rounds_today);

        // 钱包、积分流水、累计收益都不许动
        self::assertSame(0, (int) PointWallet::query()->where('user_id', $female->id)->value('balance'));
        self::assertSame(0, PointLog::query()->where('user_id', $female->id)->count());
        self::assertSame(0, CoinWallet::query()->where('user_id', $female->id)->count());
        self::assertSame(0, (int) $female->refresh()->total_earned_points);

        // 女号没开 AI 代聊：只有男号那一条
        self::assertSame(1, Message::query()->count());
        self::assertSame(0, AiReplyLog::query()->count());
    }

    public function testDailyLimitStopsTheFemaleForTheRestOfTheDay(): void
    {
        // 1 分 = 1 积分，第一条（15 积分）就会顶到上限
        $rule = $this->makeRule(['daily_amount_limit_fen' => 1]);
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        self::assertTrue($this->tick($rule, $target, [$male]));
        self::assertFalse($this->tick($rule, $target, [$male]), '达到上限后当天不该再发');

        self::assertSame(1, Message::query()->count());
        $target->refresh();
        self::assertSame(BotChatTarget::STOP_DAILY_LIMIT, $target->stopped_reason);
    }

    public function testRoundLimitStopsThePairButNotOtherFemales(): void
    {
        $rule = $this->makeRule(['round_limit_per_pair' => 1]);
        $femaleA = $this->makeFemale();
        $femaleB = $this->makeFemale();
        $targetA = $this->makeTarget($rule->id, $femaleA->id);
        $targetB = $this->makeTarget($rule->id, $femaleB->id);
        $male = $this->makeMale();

        self::assertTrue($this->tick($rule, $targetA, [$male]));
        self::assertFalse($this->tick($rule, $targetA, [$male]), '这一对轮数用完就该停');

        self::assertTrue($this->tick($rule, $targetB, [$male]), '别的女号不受影响');
        self::assertSame(2, Message::query()->count());
    }

    public function testIntervalBlocksImmediateSecondSend(): void
    {
        $rule = $this->makeRule(['interval_seconds' => 3600]);
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        self::assertTrue($this->tick($rule, $target, [$male]));
        self::assertFalse($this->tick($rule, $target, [$male]), '间隔没到不能再发');
        self::assertSame(1, Message::query()->count());
    }

    public function testActiveWindowBlocksOutsideHours(): void
    {
        $hour = (int) date('G');
        $start = ($hour + 2) % 24;
        $end = ($hour + 4) % 24;

        $rule = $this->makeRule(['window_start' => $start, 'window_end' => $end]);
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        self::assertFalse($this->tick($rule, $target, [$male]));
        self::assertSame(0, Message::query()->count());
    }

    public function testGlobalSwitchOffStopsEverything(): void
    {
        make(AppConfigService::class)->set('bot_chat.enabled', '0');

        $rule = $this->makeRule();
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        self::assertFalse($this->tick($rule, $target, [$male]));
        self::assertSame(0, Message::query()->count());
    }

    public function testBlockedFemaleIsSkipped(): void
    {
        $rule = $this->makeRule();
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        \App\Model\Cl\Block::query()->create([
            'user_id' => $female->id,
            'target_id' => $male->id,
        ]);

        self::assertFalse($this->tick($rule, $target, [$male]));
        self::assertSame(0, Message::query()->count());
    }

    public function testCanFemaleReplyFollowsTheRuleLimit(): void
    {
        $rule = $this->makeRule(['round_limit_per_pair' => 2]);
        $female = $this->makeFemale();
        $target = $this->makeTarget($rule->id, $female->id);
        $male = $this->makeMale();

        $today = date('Y-m-d');
        // 模拟「今天已经跑过一轮」：stat_date 不是今天时按「还没重置」处理，直接放行
        $target->update(['stat_date' => $today]);
        // 男号还没发：rounds = 0 <= 2
        self::assertTrue($this->service->canFemaleReply((int) $rule->id, $male->id, $female->id, $today));

        BotChatPair::query()->create([
            'rule_id' => $rule->id,
            'male_id' => $male->id,
            'female_id' => $female->id,
            'stat_date' => $today,
            'rounds_today' => 2,
        ]);
        // 男号那条已经把轮数计进去了，等于上限时最后一轮的女号回复还要能发
        self::assertTrue($this->service->canFemaleReply((int) $rule->id, $male->id, $female->id, $today));

        BotChatPair::query()->where('rule_id', $rule->id)->update(['rounds_today' => 3]);
        self::assertFalse($this->service->canFemaleReply((int) $rule->id, $male->id, $female->id, $today));
    }

    // ---------- 辅助 ----------

    /** @param array<int,User> $males */
    private function tick(BotChatRule $rule, BotChatTarget $target, array $males): bool
    {
        return $this->service->tick($rule, $target, $males, date('Y-m-d'));
    }

    /**
     * 把模型客户端换成固定回复的假实现 —— 测试不碰外网。
     *
     * `DeepSeekClient` 是 final 的，但它依赖的 `ClientFactory` 不是：换掉工厂就能让所有调用拿到 MockHandler。
     *
     * **两处都要绑**：只绑工厂的话，全套件跑下来 `DeepSeekClient` 可能已经被别的用例解析过、
     * 存在容器里，这时拿到的还是真工厂（请求打到外网、失败返回 null，表现就是「一条都发不出去」）。
     */
    private function bindFakeAiClient(): void
    {
        $container = ApplicationContext::getContainer();
        $factory = new class ($container) extends ClientFactory {
            public function create(array $options = []): Client
            {
                $mock = new MockHandler([
                    new Response(200, [], (string) json_encode([
                        'choices' => [['message' => ['content' => '在干嘛呢']]],
                        'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 6],
                    ], JSON_UNESCAPED_UNICODE)),
                ]);

                return new Client(['handler' => HandlerStack::create($mock)]);
            }
        };

        $container->set(ClientFactory::class, $factory);
        $container->set(DeepSeekClient::class, new DeepSeekClient(
            $factory,
            make(ConfigInterface::class),
            make(AppConfigService::class),
            make(LoggerInterface::class),
        ));
    }

    /** @param array<string,mixed> $overrides */
    private function makeRule(array $overrides = []): BotChatRule
    {
        /** @var BotChatRule $rule */
        $rule = BotChatRule::query()->create(array_merge([
            'name' => '测试规则',
            'enabled' => BotChatRule::STATUS_RUNNING,
            'male_user_ids' => [],
            'pool_male_count' => 0,
            'window_start' => 0,
            'window_end' => 0,
            'round_limit_per_pair' => 20,
            'interval_seconds' => 0,
            'daily_amount_limit_fen' => 1500,
            'context_messages' => 10,
        ], $overrides));

        return $rule->refresh();
    }

    private function makeTarget(int $ruleId, int $femaleId): BotChatTarget
    {
        /** @var BotChatTarget $target */
        $target = BotChatTarget::query()->create([
            'rule_id' => $ruleId,
            'female_id' => $femaleId,
            'enabled' => 1,
        ]);

        return $target->refresh();
    }

    private function makeMale(): User
    {
        $index = ++$this->sequence;

        /** @var User $male */
        $male = User::query()->create([
            'account' => 'botmale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '男号' . $index,
            'avatar' => '',
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
        ]);

        return $male->refresh();
    }

    private function makeFemale(bool $realNamePassed = true): User
    {
        $index = ++$this->sequence;

        /** @var User $female */
        $female = User::query()->create([
            'account' => 'botfemale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女号' . $index,
            'avatar' => '',
            'level' => 1,
            'real_name_status' => $realNamePassed ? User::REAL_NAME_PASSED : User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);

        return $female->refresh();
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

    private function seedConfig(): void
    {
        $config = make(AppConfigService::class);
        $config->set('bot_chat.enabled', '1');
        $config->set('ai.api_key', 'test-key');
        $config->set('ai.base_url', 'https://ai.test');
        $config->set('ai.model', 'test-model');
        $config->set('point.per_yuan', 100);
        $config->set('coin.per_yuan', 10);
        $config->set('share.unverified_factor', 5000);
    }

    private function clearAll(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();
        SensitiveWord::query()->delete();
        \App\Model\Cl\Block::query()->delete();
        AiReplyLog::query()->delete();
        \App\Model\Cl\AiPersona::query()->delete();
        BotChatPair::query()->delete();
        BotChatTarget::query()->delete();
        BotChatRule::query()->delete();
        PointLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();

        make(SensitiveWordService::class)->flush();
    }
}
