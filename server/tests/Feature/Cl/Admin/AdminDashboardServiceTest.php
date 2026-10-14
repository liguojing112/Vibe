<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Cl\Message;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\RealName;
use App\Model\Cl\Report;
use App\Model\Cl\User;
use App\Model\Cl\WithdrawOrder;
use App\Service\Cl\Admin\AdminDashboardService;
use App\Service\Cl\Admin\AdminFinanceService;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\PlatformIncomeService;
use Hyperf\DbConnection\Db;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 数据看板（docs/07 第 3.1 节）。
 *
 * 这里最要紧的三件事，都是「看板上的数跟别处对不上」这类问题的根子：
 * 1. **窗口边界**：卡片只算今天，图只算近 30 天（第 31 天前的不进图）；
 * 2. **补零**：某天没数据也必须在序列里占一格是 0，不能整格消失（缺天的话折线会把两天连起来，看着像天天有量）；
 * 3. **口径一致**：看板的「平台毛收入」必须和「平台收入」页算出同一个数（公式只有一份）。
 *
 * 断言全部用**增量**而不是绝对值 —— 测试库是各测试类共用的，写死数字的话运行顺序一变就挂。
 * 建出来的行都在 tearDown 里按 id 删掉，不动别的测试类的数据。
 *
 * @internal
 * @coversNothing
 */
final class AdminDashboardServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminDashboardService $service;

    /** 建数据之前的卡片（算增量用）。 */
    private array $baseline = [];

    /** 本用例建出来的行，tearDown 时按 id 删（只删自己建的）。 */
    private array $created = [];

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = make(AdminDashboardService::class);
        $this->baseline = $this->cards();
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $table => $ids) {
            if ($ids !== []) {
                Db::table($table)->whereIn('id', $ids)->delete();
            }
        }
        $this->created = [];
        parent::tearDown();
    }

    // ---------- 卡片：今日新增 / 活跃 ----------

    public function testNewUsersCountMaleAndFemaleSeparatelyForTodayOnly(): void
    {
        $this->makeUser(User::GENDER_MALE);
        $this->makeUser(User::GENDER_FEMALE);
        $this->makeUser(User::GENDER_FEMALE);
        // 昨天注册的**不能**算进「今日新增」——它还在 30 天窗口里，只有按天筛能挡住它
        $this->makeUser(User::GENDER_MALE, $this->at('-1 day'));

        $after = $this->cards();

        self::assertSame(1, $after['new_male'] - $this->baseline['new_male'], '今日新增男用户');
        self::assertSame(2, $after['new_female'] - $this->baseline['new_female'], '今日新增女用户');
    }

    public function testActiveUsersOnlyCountWhoWasActiveToday(): void
    {
        $this->makeUser(User::GENDER_MALE, null, ['last_active_at' => $this->at('now')]);
        $this->makeUser(User::GENDER_FEMALE, null, ['last_active_at' => $this->at('now')]);
        $this->makeUser(User::GENDER_MALE, null, ['last_active_at' => $this->at('-1 day')]);
        // 从没活跃过的（批量开号刚建出来还没登录）不算
        $this->makeUser(User::GENDER_MALE, null, ['last_active_at' => null]);

        self::assertSame(2, $this->cards()['active_users'] - $this->baseline['active_users'], '今日活跃用户');
    }

    // ---------- 卡片：今日消费与充值 ----------

    /** 免费私信（`price_coins = 0`）不算「付费私信条数」。 */
    public function testPaidMessagesExcludeFreeOnes(): void
    {
        $this->makeMessage(priceCoins: 5);
        $this->makeMessage(priceCoins: 5);
        $this->makeMessage(priceCoins: 0);

        self::assertSame(2, $this->cards()['paid_messages'] - $this->baseline['paid_messages'], '只有真扣了钱的才算');
    }

    public function testGiftCoinsSumTodayOnly(): void
    {
        $this->makeGiftRecord(66);
        $this->makeGiftRecord(88);
        $this->makeGiftRecord(520, $this->at('-1 day'));
        $this->makeGiftRecord(999, $this->at('-40 days'));

        self::assertSame(154, $this->cards()['gift_coins'] - $this->baseline['gift_coins']);
    }

    /** 赠送单（`amount_fen = 0`）金币要算，金额不加 —— 只筛「有金额的」会把赠送整单漏掉。 */
    public function testRechargeCountsCoinsAndReceivedAmount(): void
    {
        $this->makeRechargeOrder(coins: 1000, amountFen: 10000, channel: 'WECHAT');
        $this->makeRechargeOrder(coins: 200, amountFen: 0, channel: 'GIFT');

        $after = $this->cards();

        self::assertSame(1200, $after['recharge_coins'] - $this->baseline['recharge_coins'], '含赠送的金币');
        self::assertSame(10000, $after['recharge_amount_fen'] - $this->baseline['recharge_amount_fen'], '实收金额只算真收的钱');
    }

    // ---------- 卡片：平台毛收入 ----------

    /**
     * 公式本身（和「平台收入」页共用 `PlatformIncomeService::incomeFen`）：
     * 100 金币 ÷10 = ¥10.00，40 积分 ÷100 = ¥0.40 → 平台留 ¥9.60 = 960 分。
     */
    public function testIncomeFormulaKeepsFenAndMatchesTheFinancePage(): void
    {
        self::assertSame(960, PlatformIncomeService::incomeFen(100, 40, 10, 100), '毛收入公式');

        $today = date('Y-m-d');
        $config = make(AppConfigService::class);
        $coinPerYuan = (int) $config->get('coin.per_yuan', 10);
        $pointPerYuan = (int) $config->get('point.per_yuan', 100);

        $this->makePlatformIncome(100, 40, $today);

        $finance = make(AdminFinanceService::class)->incomes($today, $today, $coinPerYuan, $pointPerYuan);

        self::assertSame(
            $finance['total_income_fen'],
            $this->cards()['platform_income_fen'],
            '看板和「平台收入」页必须是同一个数（公式抄第二份的话这条就会红）',
        );
    }

    // ---------- 卡片：待办 ----------

    public function testPendingCountsForEveryTodoCard(): void
    {
        $this->makeProfileAudit();
        $this->makeRealName();
        $this->makeReport();
        $this->makeMessage(priceCoins: 5, reviewStatus: Message::REVIEW_PENDING);
        $this->makeWithdrawOrder(amountFen: 5000);

        $after = $this->cards();

        self::assertSame(1, $after['pending_profile'] - $this->baseline['pending_profile'], '待审资料');
        self::assertSame(1, $after['pending_realname'] - $this->baseline['pending_realname'], '待审实名');
        self::assertSame(1, $after['pending_reports'] - $this->baseline['pending_reports'], '待处理举报');
        self::assertSame(1, $after['pending_messages'] - $this->baseline['pending_messages'], '待审消息');
        self::assertSame(1, $after['pending_withdraw_count'] - $this->baseline['pending_withdraw_count'], '待审核提现笔数');
        // 报的是用户**申请**的金额（财务要照着准备钱），不是扣完手续费的到账金额
        self::assertSame(5000, $after['pending_withdraw_fen'] - $this->baseline['pending_withdraw_fen'], '待审核提现金额');
    }

    // ---------- 图表 ----------

    /** 近 30 天：**连续 30 个点，最后一个是今天**，四个序列长度一致。 */
    public function testChartCoversThirtyContinuousDaysEndingToday(): void
    {
        $chart = $this->overview()['chart'];

        self::assertCount(30, $chart['dates']);
        self::assertSame(date('Y-m-d'), $chart['dates'][29], '最后一个点是今天');
        self::assertSame(date('Y-m-d', strtotime('-29 days')), $chart['dates'][0], '第一个点是 29 天前');

        // 一天不落：每格比前一格正好晚一天
        foreach ($chart['dates'] as $i => $date) {
            if ($i > 0) {
                self::assertSame(
                    date('Y-m-d', strtotime($chart['dates'][$i - 1] . ' +1 day')),
                    $date,
                    "第 {$i} 格和上一格必须正好差一天（中间不能缺天）",
                );
            }
        }

        foreach (['new_male', 'new_female', 'recharge_amount_fen', 'platform_income_fen'] as $key) {
            self::assertCount(30, $chart[$key], "{$key} 序列要和日期一一对应，没有数据的天也要占一格");
        }
    }

    /** 只有今天有数据时，**只有今天那一格变**，前面 29 天不动（说明没数据的天是 0，不是少一格）。 */
    public function testChartOnlyPlotsTodayWhenOnlyTodayHasData(): void
    {
        $before = $this->overview()['chart'];
        $this->makeRechargeOrder(coins: 500, amountFen: 5000, channel: 'ALIPAY');
        $after = $this->overview()['chart'];

        $diff = [];
        foreach ($after['recharge_amount_fen'] as $i => $value) {
            $delta = (int) $value - (int) $before['recharge_amount_fen'][$i];
            if ($delta !== 0) {
                $diff[$i] = $delta;
            }
        }

        self::assertSame([29 => 5000], $diff, '只有今天这格变，其它天不受影响');
    }

    /** 图最后一个点和「今日」卡片是同一批数据，不能各算各的。 */
    public function testChartLastPointAgreesWithTodayCards(): void
    {
        $this->makeRechargeOrder(coins: 500, amountFen: 5000, channel: 'ALIPAY');

        $overview = $this->overview();

        self::assertSame(
            (int) $overview['cards']['recharge_amount_fen'] - $this->baseline['recharge_amount_fen'],
            (int) $overview['chart']['recharge_amount_fen'][29],
            '充值实收：卡片和图的最后一点必须一致',
        );
    }

    /** 第 31 天前的数据不能混进图里（窗口外的充值不影响任何一格）。 */
    public function testOutOfWindowDataIsNotPlotted(): void
    {
        $before = $this->overview();
        $this->makeRechargeOrder(coins: 999, amountFen: 99900, channel: 'WECHAT', createdAt: $this->at('-35 days'));
        $after = $this->overview();

        self::assertSame(
            $before['chart']['recharge_amount_fen'],
            $after['chart']['recharge_amount_fen'],
            '35 天前的充值在窗口外，一格都不该动',
        );
        self::assertSame(
            $before['cards']['recharge_amount_fen'],
            $after['cards']['recharge_amount_fen'],
            '窗口外的充值也不算今日实收',
        );
        self::assertCount(30, $after['chart']['recharge_amount_fen']);
    }

    // ---------- 工具 ----------

    private function overview(): array
    {
        return $this->service->overview();
    }

    private function cards(): array
    {
        return $this->overview()['cards'];
    }

    /** 「今天某个时刻」用 `now`，其余给 `strtotime` 能认的相对时间（`-1 day` / `-40 days`）。 */
    private function at(string $when): string
    {
        return date('Y-m-d H:i:s', $when === 'now' ? time() : (int) strtotime($when));
    }

    private function track(string $table, int $id): void
    {
        $this->created[$table][] = $id;
    }

    private function makeUser(int $gender, ?string $createdAt = null, array $overrides = []): int
    {
        $at = $createdAt ?? date('Y-m-d H:i:s');
        $account = '9' . str_pad((string) ($this->sequence++), 9, '0', STR_PAD_LEFT);

        $id = (int) Db::table('cl_user')->insertGetId(array_merge([
            'account' => $account,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $gender === User::GENDER_MALE ? '看板男' : '看板女',
            'status' => User::STATUS_NORMAL,
            'source' => 1,
            'level' => 1,
            'created_at' => $at,
            'updated_at' => $at,
        ], $overrides));
        $this->track('cl_user', $id);

        return $id;
    }

    private function makeMessage(int $priceCoins, int $reviewStatus = Message::REVIEW_NONE, ?string $createdAt = null): int
    {
        $at = $createdAt ?? date('Y-m-d H:i:s');
        $id = (int) Db::table('cl_message')->insertGetId([
            'conversation_id' => 1,
            'sender_id' => 1,
            'receiver_id' => 2,
            'client_msg_id' => 'dl' . str_pad((string) ($this->sequence++), 8, '0', STR_PAD_LEFT),
            'type' => Message::TYPE_TEXT,
            'content' => '看板测试',
            'price_coins' => $priceCoins,
            'free_reason' => $priceCoins > 0 ? null : 'VIP_FREE',
            'receiver_points' => 0,
            'sensitive_hit' => $reviewStatus === Message::REVIEW_PENDING ? 1 : 0,
            'review_status' => $reviewStatus,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->track('cl_message', $id);

        return $id;
    }

    /** `gift_id` 没有外键，礼物名是冗余存在送出记录里的，所以不用真建一条 `cl_gift`。 */
    private function makeGiftRecord(int $priceCoins, ?string $createdAt = null): int
    {
        $at = $createdAt ?? date('Y-m-d H:i:s');
        $id = (int) Db::table('cl_gift_record')->insertGetId([
            'client_order_id' => 'dl' . str_pad((string) ($this->sequence++), 8, '0', STR_PAD_LEFT),
            'sender_id' => 1,
            'receiver_id' => 2,
            'gift_id' => 1,
            'gift_name' => '看板礼物',
            'price_coins' => $priceCoins,
            'share_rate' => 3000,
            'receiver_points' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->track('cl_gift_record', $id);

        return $id;
    }

    private function makeRechargeOrder(int $coins, int $amountFen, string $channel, ?string $createdAt = null): int
    {
        $at = $createdAt ?? date('Y-m-d H:i:s');
        $id = (int) Db::table('cl_recharge_order')->insertGetId([
            'order_no' => 'R' . date('YmdHis') . str_pad((string) ($this->sequence++), 4, '0', STR_PAD_LEFT),
            'user_id' => 1,
            'coins' => $coins,
            'amount_fen' => $amountFen,
            'pay_channel' => $channel,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $this->track('cl_recharge_order', $id);

        return $id;
    }

    private function makePlatformIncome(int $coins, int $points, string $date): int
    {
        $id = (int) Db::table('cl_platform_income')->insertGetId([
            'source' => 'MESSAGE',
            'ref_id' => 100000 + ($this->sequence++),
            'payer_id' => 1,
            'receiver_id' => 2,
            'coins' => $coins,
            'receiver_points' => $points,
            'income_date' => $date,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->track('cl_platform_income', $id);

        return $id;
    }

    private function makeProfileAudit(): int
    {
        $id = (int) Db::table('cl_profile_audit')->insertGetId([
            'user_id' => 1,
            'field' => ProfileAudit::FIELD_NICKNAME,
            'new_value' => '看板改名',
            'status' => ProfileAudit::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->track('cl_profile_audit', $id);

        return $id;
    }

    private function makeRealName(): int
    {
        $id = (int) Db::table('cl_real_name')->insertGetId([
            'user_id' => 1,
            'real_name' => '看板',
            'id_card_encrypted' => 'x',
            'id_card_mask' => '110***********1234',
            'id_card_hash' => hash('sha256', 'dl' . ($this->sequence++)),
            'status' => RealName::STATUS_AUDITING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->track('cl_real_name', $id);

        return $id;
    }

    private function makeReport(): int
    {
        $id = (int) Db::table('cl_report')->insertGetId([
            'reporter_id' => 1,
            'target_id' => 2,
            'reason' => Report::REASON_FRAUD,
            'content' => '看板测试',
            'status' => Report::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->track('cl_report', $id);

        return $id;
    }

    private function makeWithdrawOrder(int $amountFen): int
    {
        $id = (int) Db::table('cl_withdraw_order')->insertGetId([
            'order_no' => 'W' . date('YmdHis') . str_pad((string) ($this->sequence++), 4, '0', STR_PAD_LEFT),
            'user_id' => 2,
            'amount_fen' => $amountFen,
            'points' => intdiv($amountFen, 10),
            'fee_fen' => 0,
            'actual_fen' => $amountFen,
            'account_type' => 'ALIPAY',
            'account_no' => 'user@example.com',
            'account_name' => '看板女',
            'status' => WithdrawOrder::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->track('cl_withdraw_order', $id);

        return $id;
    }
}
