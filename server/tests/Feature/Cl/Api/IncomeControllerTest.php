<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\GiftRecord;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Message;
use App\Model\Cl\Notice;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Enums\Cl\PointLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 收益与等级接口（docs/05 第 4.8 节）：`/income/summary`、`/income/logs`、`/income/level`。
 *
 * 三个都是只读接口，这里盯的是**算出来的数对不对**（约合人民币、今日三项、实际分成）和鉴权。
 *
 * @internal
 * @coversNothing
 */
final class IncomeControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->clearAll();
        $this->seedLevels();

        $config = make(AppConfigService::class);
        $config->set('point.per_yuan', 100);
        $config->set('coin.per_yuan', 10);
        $config->set('share.gift_rate', 3400);
        $config->set('share.unverified_apply_gift', 0);
        $config->set('share.unverified_factor', 5000);
        $config->set('feature.call_enabled', 0);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testSummaryNumbers(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);
        // 两条私信收益 + 一条礼物收益：15 + 15 + 408 = 438
        $this->giveIncome($female, 15, '私信 · 阿宁');
        $this->giveIncome($female, 15, '私信 · 林川');
        $this->giveIncome($female, 408, '礼物收入 · 浪漫花束', PointLogType::GIFT_INCOME);
        // 一条任务奖励：不算「收益」（和 total_earned_points 一个口径）
        $this->giveIncome($female, 20, '任务奖励 · 每日签到', PointLogType::TASK);
        $this->addMessage($female, fromUserId: 9001);
        $this->addMessage($female, fromUserId: 9002);
        $this->addGiftRecord($female);

        $data = $this->get($female, '/api/v1/income/summary')['data'];

        self::assertSame(458, $data['balance'], '15 + 15 + 408 + 20');
        self::assertSame(0, $data['frozen']);
        self::assertSame(458, $data['yuan_fen'], '458 积分 ÷ 100 = ¥4.58');
        self::assertSame(438, $data['today_points'], '今日收益不算任务奖励');
        self::assertSame(2, $data['today_messages']);
        self::assertSame(1, $data['today_gifts']);
    }

    public function testLogsArePagedNewestFirst(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);
        $this->giveIncome($female, 15, '私信 · 阿宁');
        $this->giveIncome($female, 408, '礼物收入 · 浪漫花束', PointLogType::GIFT_INCOME);

        $data = $this->get($female, '/api/v1/income/logs')['data'];

        self::assertSame(2, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertCount(2, $data['list']);
        self::assertSame('礼物收入 · 浪漫花束', $data['list'][0]['title'], '最新的在最前');
        self::assertSame(408, $data['list'][0]['change']);
        self::assertSame(423, $data['list'][0]['balance_after']);
        self::assertSame(PointLogType::GIFT_INCOME->value, $data['list'][0]['type']);
        self::assertNotNull($data['list'][0]['created_at']);
    }

    public function testLevelShowsPriceShareAndNextThreshold(): void
    {
        // 未实名的 Lv3：等级表写 4000，实际只能拿 2000（一半）
        $female = $this->registerUser(User::GENDER_FEMALE);
        User::query()->whereKey($female->id)->update([
            'level' => 3,
            'total_earned_points' => 120000,
            'real_name_status' => User::REAL_NAME_NONE,
        ]);

        $data = $this->get($female, '/api/v1/income/level')['data'];

        self::assertSame(3, $data['level']);
        self::assertSame('Lv3', $data['name']);
        self::assertSame(120000, $data['total_earned_points']);
        self::assertFalse($data['level_locked']);
        self::assertSame(4, $data['next_level']);
        self::assertSame(300000, $data['next_threshold']);
        self::assertSame(180000, $data['points_to_next']);
        self::assertSame(10, $data['progress_percent'], '(120000-100000) ÷ (300000-100000) = 10%');
        self::assertSame(10, $data['message_price']);
        self::assertSame(4000, $data['share_rate'], '等级表里写的分成');
        self::assertSame(2000, $data['actual_share_rate'], '未实名打五折');
        self::assertSame(3400, $data['gift_share_rate'], '礼物分成是全局配置，未实名默认不打折');
        self::assertFalse($data['real_name_passed']);
        self::assertFalse($data['call_enabled']);
        self::assertCount(5, $data['levels']);
        self::assertSame(
            ['level' => 1, 'threshold_points' => 0, 'message_price' => 5, 'message_share_rate' => 3000],
            [
                'level' => $data['levels'][0]['level'],
                'threshold_points' => $data['levels'][0]['threshold_points'],
                'message_price' => $data['levels'][0]['message_price'],
                'message_share_rate' => $data['levels'][0]['message_share_rate'],
            ],
        );
    }

    public function testTopLevelHasNoNext(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);
        User::query()->whereKey($female->id)->update(['level' => 5, 'total_earned_points' => 1000000]);

        $data = $this->get($female, '/api/v1/income/level')['data'];

        self::assertNull($data['next_level']);
        self::assertNull($data['next_threshold']);
        self::assertSame(0, $data['points_to_next']);
        self::assertSame(100, $data['progress_percent']);
    }

    public function testMaleCannotReadIncome(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);

        foreach (['/api/v1/income/summary', '/api/v1/income/logs', '/api/v1/income/level'] as $url) {
            $result = $this->get($male, $url);
            self::assertSame(40001, $result['code'], $url);
            self::assertSame('收益与等级只有女用户有', $result['message']);
        }
    }

    public function testNeedLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/income/summary')['code']);
        self::assertSame(401, $this->client->get('/api/v1/income/level')['code']);
    }

    // ---------- 工具 ----------

    private function giveIncome(User $user, int $points, string $title, PointLogType $type = PointLogType::MESSAGE_INCOME): void
    {
        make(PointWalletService::class)->increase(
            $user->id,
            $points,
            $type,
            'test',
            ++$this->sequence,
            $title,
        );
    }

    private function addMessage(User $receiver, int $fromUserId): void
    {
        Message::query()->create([
            'conversation_id' => 1,
            'sender_id' => $fromUserId,
            'receiver_id' => $receiver->id,
            'client_msg_id' => 'i' . (++$this->sequence),
            'type' => Message::TYPE_TEXT,
            'content' => '你好',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function addGiftRecord(User $receiver): void
    {
        GiftRecord::query()->create([
            'client_order_id' => 'g' . (++$this->sequence),
            'sender_id' => 9001,
            'receiver_id' => $receiver->id,
            'gift_id' => 1,
            'gift_name' => '一朵玫瑰',
            'price_coins' => 10,
            'share_rate' => 3400,
            'receiver_points' => 34,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], ['Authorization' => 'Bearer ' . $this->tokens[$user->id]]);
    }

    private function registerUser(int $gender): User
    {
        $phone = '13700000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => '用户' . $this->sequence,
        ]);
        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $this->tokens[$user->id] = (string) $result['data']['access_token'];

        return $user;
    }

    private function seedLevels(): void
    {
        $rows = [
            1 => [0, 5, 3000],
            2 => [20000, 8, 3500],
            3 => [100000, 10, 4000],
            4 => [300000, 15, 4500],
            5 => [1000000, 20, 5000],
        ];

        // 按 level 覆盖写、**不删整表**：`cl_level_config` 是别的测试类也在用的共享种子，
        // 删掉它会让后跑的用例拿不到等级配置（`GiftBillingTest` 也是这么干的）。
        foreach ($rows as $level => [$threshold, $price, $rate]) {
            $attributes = [
                'name' => 'Lv' . $level,
                'threshold_points' => $threshold,
                'message_price' => $price,
                'message_share_rate' => $rate,
                'voice_price' => 60 * $level,
                'video_price' => 120 * $level,
            ];
            $existing = LevelConfig::query()->where('level', $level)->first();
            if ($existing === null) {
                LevelConfig::query()->create(array_merge(['level' => $level], $attributes));
            } else {
                $existing->update($attributes);
            }
        }
    }

    private function clearAll(): void
    {
        Notice::query()->delete();
        GiftRecord::query()->delete();
        Message::query()->delete();
        PointLog::query()->delete();
        CoinLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
