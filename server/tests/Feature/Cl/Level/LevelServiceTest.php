<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Level;

use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Service\Cl\Level\LevelService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 女用户等级（docs/06 第 5 节 + 第 10 节场景 17~19 的服务层部分）。
 *
 * 三条硬规则在这里直接盯着 `LevelService` 断言（不绕发消息那条路）：
 * - **只升不降**；
 * - 锁定等级后自动升级不再生效；
 * - **升级时发一条系统通知**（场景 17 的「并发通知」这句）。
 *
 * @internal
 * @coversNothing
 */
final class LevelServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private LevelService $service;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->seedLevels();
        $this->service = make(LevelService::class);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testUpgradeSendsSystemNotice(): void
    {
        $female = $this->makeFemale(level: 1, earned: 20000);

        $target = $this->service->checkUpgrade($female);

        self::assertNotNull($target);
        self::assertSame(2, (int) $female->refresh()->level);

        $notice = Notice::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame(Notice::CATEGORY_SYSTEM, (int) $notice->category);
        self::assertSame('等级提升', $notice->title);
        self::assertSame('恭喜你升到 Lv2，私信单价和分成比例已按新等级生效', $notice->content);
        self::assertSame(Notice::BIZ_LEVEL_UP, $notice->biz_type);
        self::assertSame(2, (int) $notice->biz_id);
        self::assertNull($notice->read_at, '刚发出来是未读');
    }

    public function testLevelNeverGoesDown(): void
    {
        // 后台手动提到 Lv4 之后，累计收益掉到 Lv1 的水位也不该降回去
        $female = $this->makeFemale(level: 4, earned: 100);

        self::assertNull($this->service->checkUpgrade($female));
        self::assertSame(4, (int) $female->refresh()->level);
        self::assertSame(0, Notice::query()->where('user_id', $female->id)->count(), '没升级就不该发通知');
    }

    public function testLockedLevelDoesNotUpgrade(): void
    {
        $female = $this->makeFemale(level: 1, earned: 999999, locked: true);

        self::assertNull($this->service->checkUpgrade($female));
        self::assertSame(1, (int) $female->refresh()->level);
        self::assertSame(0, Notice::query()->where('user_id', $female->id)->count());
    }

    public function testMaleNeverUpgrades(): void
    {
        $male = $this->makeMale(earned: 999999);

        self::assertNull($this->service->checkUpgrade($male));
        self::assertSame(1, (int) $male->refresh()->level, '男用户的等级固定 1，不使用');
    }

    public function testCanJumpSeveralLevelsAndOnlyNotifiesOnce(): void
    {
        $female = $this->makeFemale(level: 1, earned: 399990);

        $target = $this->service->checkUpgrade($female);

        self::assertSame(4, (int) $target?->level, '一次跨过 20000 与 100000 两档，直接到满足的最高级');
        self::assertSame(1, Notice::query()->where('user_id', $female->id)->count(), '跨级也只发一条通知');
    }

    public function testRepeatedCheckDoesNotNotifyAgain(): void
    {
        $female = $this->makeFemale(level: 1, earned: 20000);

        $this->service->checkUpgrade($female);
        self::assertNull($this->service->checkUpgrade($female->refresh()), '已经是 Lv2 了，再检查也没有升级');
        self::assertSame(1, Notice::query()->where('user_id', $female->id)->count());
    }

    /**
     * 后台手动调高等级（`06` §5）后，累计收益低于该级门槛也不能被降回去 —— 只升不降。
     * 演示数据里的晚晴就是 Lv3 而累计收益只有 1244，真机会看到这个状态。
     */
    public function testManualHigherLevelIsNotDowngraded(): void
    {
        $female = $this->makeFemale(level: 3, earned: 1244);

        self::assertNull($this->service->checkUpgrade($female));
        self::assertSame(3, (int) $female->refresh()->level);
    }

    public function testBelowThresholdDoesNotUpgrade(): void
    {
        $female = $this->makeFemale(level: 1, earned: 19999);

        self::assertNull($this->service->checkUpgrade($female));
        self::assertSame(1, (int) $female->refresh()->level);
    }

    // ---------- 工具 ----------

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

    private function makeFemale(int $level, int $earned, bool $locked = false): User
    {
        $index = ++$this->sequence;
        $female = User::query()->create([
            'account' => 'lvfemale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女' . $index,
            'level' => $level,
            'level_locked' => $locked ? 1 : 0,
            'total_earned_points' => $earned,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);

        return $female->refresh();
    }

    private function makeMale(int $earned): User
    {
        $index = ++$this->sequence;
        $male = User::query()->create([
            'account' => 'lvmale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '男' . $index,
            'level' => 1,
            'total_earned_points' => $earned,
            'status' => User::STATUS_NORMAL,
        ]);

        make(CoinWalletService::class)->createForUser($male->id);

        return $male->refresh();
    }

    private function clearAll(): void
    {
        Notice::query()->delete();
        CoinLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
