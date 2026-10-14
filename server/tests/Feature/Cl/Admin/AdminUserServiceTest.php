<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Cl\VipOrder;
use App\Model\Cl\VipPlan;
use App\Model\Permission\User as AdminUser;
use App\Model\Enums\Cl\BizCode;
use App\Service\Cl\Admin\AdminUserService;
use App\Service\Cl\Auth\AppAuthService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 后台用户管理（docs/07 第 3.2 节）。
 *
 * 这里盯的是**后台动手之后用户那边发生了什么**：
 * 封禁有没有真的把人挡在外面、调等级/开会员是不是改在了对的地方、
 * 每次动作有没有留下能查的痕迹（`cl_admin_action_log`）。
 *
 * @internal
 * @coversNothing
 */
final class AdminUserServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminUserService $service;

    private AdminUser $operator;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminUserService::class);
        $this->operator = AdminUser::create(['username' => 'kefu', 'password' => '123456', 'nickname' => '客服']);
    }

    protected function tearDown(): void
    {
        $this->clear();
        AdminUser::query()->where('username', 'kefu')->delete();
        parent::tearDown();
    }

    // ---------- 列表与详情 ----------

    public function testListOnlyReturnsTheRequestedGender(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');
        $this->makeUser(User::GENDER_FEMALE, '晚晴');

        $malePage = $this->service->list(['gender' => User::GENDER_MALE], 1, 20);
        $femalePage = $this->service->list(['gender' => User::GENDER_FEMALE], 1, 20);

        self::assertSame(1, $malePage['total']);
        self::assertSame($male->id, (int) $malePage['list'][0]['id']);
        self::assertSame(1, $femalePage['total']);
        self::assertSame('晚晴', $femalePage['list'][0]['nickname']);
    }

    public function testListFiltersByKeywordStatusAndVip(): void
    {
        $vip = $this->makeUser(User::GENDER_MALE, '有会员的', coins: 100, vipDays: 30);
        $this->makeUser(User::GENDER_MALE, '没会员的', coins: 50);

        self::assertSame(1, $this->service->list(['gender' => 1, 'nickname' => '有会员'], 1, 20)['total']);
        self::assertSame(1, $this->service->list(['gender' => 1, 'is_vip' => 1], 1, 20)['total']);
        self::assertSame(1, $this->service->list(['gender' => 1, 'is_vip' => 0], 1, 20)['total']);

        $row = $this->service->list(['gender' => 1, 'id' => $vip->id], 1, 20)['list'][0];
        self::assertSame(100, (int) $row['coin_balance'], '列表要带钱包余额');
    }

    public function testDetailCarriesWalletsPhotosBansAndLogs(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴', points: 6264, earned: 1244);
        UserPhoto::query()->create(['user_id' => $female->id, 'url' => 'https://cdn/a.jpg', 'sort' => 1, 'audit_status' => UserPhoto::AUDIT_APPROVED]);
        UserPhoto::query()->create(['user_id' => $female->id, 'url' => 'https://cdn/pending.jpg', 'sort' => 2, 'audit_status' => UserPhoto::AUDIT_PENDING]);
        $this->service->ban($this->operator, $female->id, '测试封禁', 3);

        $detail = $this->service->detail($female->id);

        self::assertSame($female->id, $detail['user']['id']);
        self::assertSame(6264, $detail['point']['balance']);
        self::assertSame(1244, $detail['point']['totalEarned']);
        self::assertSame(['https://cdn/a.jpg'], $detail['photos'], '相册只列审核通过的');
        self::assertCount(1, $detail['bans']);
        self::assertStringContainsString('测试封禁', $detail['bans'][0]['reason']);
    }

    // ---------- 改资料 / 重置密码 ----------

    public function testUpdateProfileWritesActionLog(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->service->updateProfile($this->operator, $male->id, ['nickname' => '阿宁改名了', 'bio' => '新的简介']);

        $male->refresh();
        self::assertSame('阿宁改名了', $male->nickname);
        self::assertSame('新的简介', $male->bio);

        $log = AdminActionLog::query()->where('user_id', $male->id)->firstOrFail();
        self::assertSame(AdminActionLog::ACTION_PROFILE, $log->action);
        self::assertStringContainsString('阿宁 → 阿宁改名了', $log->detail, '日志里要能看出前后值');
        self::assertSame('kefu', $log->operator_name);
    }

    public function testUpdateProfileRejectsEmptyPayload(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        try {
            $this->service->updateProfile($this->operator, $male->id, []);
            self::fail('一个字段都没传应该报错');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }
    }

    public function testResetPasswordReturnsUsablePlainPasswordOnce(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $result = $this->service->resetPassword($this->operator, $male->id);

        self::assertSame(10, mb_strlen($result['password']));
        self::assertTrue(password_verify($result['password'], $male->refresh()->password), '返回的明文要能对上库里的哈希');
        // 库里存的必须是哈希，不是明文
        self::assertStringNotContainsString($result['password'], (string) $male->password);
    }

    // ---------- 封禁 ----------

    public function testBanSetsStatusUntilAndNotice(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->service->ban($this->operator, $male->id, '发布违规内容', 7);

        $male->refresh();
        self::assertSame(User::STATUS_BANNED, $male->status);
        self::assertSame('发布违规内容', $male->ban_reason);
        self::assertNotNull($male->ban_until);
        self::assertTrue($male->ban_until->isFuture());

        self::assertSame(1, Notice::query()->where('user_id', $male->id)->where('biz_type', 'BAN')->count(), '要发一条封禁通知');
        self::assertSame(1, AdminActionLog::query()->where('user_id', $male->id)->where('action', AdminActionLog::ACTION_BAN)->count());
    }

    public function testBanForeverLeavesUntilNull(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->service->ban($this->operator, $male->id, '屡教不改', 0);

        self::assertNull($male->refresh()->ban_until, '天数是 0 表示永久');
    }

    /**
     * 封禁要**当场**让手上的 token 失效（docs/08 T7.5）。
     *
     * 这条是这次新加的：`findActiveUser` 原来只挡已注销的，封了号的人照样能刷接口。
     */
    public function testBannedUserTokenStopsWorking(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');
        $auth = make(AppAuthService::class);

        self::assertNotNull($auth->findActiveUser($male->id), '封之前是有效的');

        $this->service->ban($this->operator, $male->id, '违规', 0);
        self::assertNull($auth->findActiveUser($male->id), '封之后 token 应该失效');

        $this->service->unban($this->operator, $male->id, '申诉通过');
        self::assertNotNull($auth->findActiveUser($male->id), '解封之后又能用了');
    }

    public function testUnbanClearsBanFields(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');
        $this->service->ban($this->operator, $male->id, '违规', 3);

        $this->service->unban($this->operator, $male->id, '误封');

        $male->refresh();
        self::assertSame(User::STATUS_NORMAL, $male->status);
        self::assertNull($male->ban_reason);
        self::assertNull($male->ban_until);
        self::assertSame(1, AdminActionLog::query()->where('user_id', $male->id)->where('action', AdminActionLog::ACTION_UNBAN)->count());
    }

    public function testUnbanRejectsUserWhoIsNotBanned(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->expectException(ClBusinessException::class);
        $this->service->unban($this->operator, $male->id, '随便解一下');
    }

    // ---------- 等级 / 会员 / 性别 ----------

    public function testSetLevelLogsBeforeAndAfter(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');

        $this->service->setLevel($this->operator, $female->id, 3, true, '运营手动扶持');

        $female->refresh();
        self::assertSame(3, $female->level);
        self::assertSame(1, $female->level_locked);

        $log = AdminActionLog::query()->where('user_id', $female->id)->firstOrFail();
        self::assertStringContainsString('Lv1 → Lv3', $log->detail);
        self::assertStringContainsString('运营手动扶持', $log->detail);
    }

    public function testSetLevelRejectsMaleUser(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->expectException(ClBusinessException::class);
        $this->service->setLevel($this->operator, $male->id, 3, false, '试试');
    }

    public function testGrantVipExtendsExpireAndWritesOrder(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁', vipDays: 10);
        $before = $male->vip_expire_at->copy();

        $this->service->grantVip($this->operator, $male->id, null, 30, '客服补偿');

        $male->refresh();
        self::assertSame(
            $before->addDays(30)->format('Y-m-d H:i'),
            $male->vip_expire_at->format('Y-m-d H:i'),
            '没过期就在原到期时间上加，不是从现在算',
        );

        $order = VipOrder::query()->where('user_id', $male->id)->firstOrFail();
        self::assertSame(2, (int) $order->source, '后台开通的 source 是 2');
        self::assertSame(0, (int) $order->price_coins, '后台开通不扣金币');
        self::assertSame($this->operator->id, (int) $order->operator_id);
    }

    public function testGrantVipByPlanUsesPlanDays(): void
    {
        $plan = VipPlan::query()->create(['name' => '月卡', 'days' => 30, 'price_coins' => 390, 'sort' => 1, 'enabled' => 1]);
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->service->grantVip($this->operator, $male->id, (int) $plan->id, 0, '');

        $order = VipOrder::query()->where('user_id', $male->id)->firstOrFail();
        self::assertSame(30, (int) $order->days);
        self::assertSame($plan->id, (int) $order->plan_id);
        self::assertNotNull($male->refresh()->vip_expire_at);
    }

    public function testGrantVipRejectsFemaleUser(): void
    {
        $female = $this->makeUser(User::GENDER_FEMALE, '晚晴');

        $this->expectException(ClBusinessException::class);
        $this->service->grantVip($this->operator, $female->id, null, 30, '');
    }

    public function testChangeGenderLogsBothSides(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->service->changeGender($this->operator, $male->id, User::GENDER_FEMALE, '注册时选错了');

        self::assertSame(User::GENDER_FEMALE, $male->refresh()->gender);
        $log = AdminActionLog::query()->where('user_id', $male->id)->firstOrFail();
        self::assertStringContainsString('男 → 女', $log->detail);
    }

    public function testChangeGenderRejectsSameGender(): void
    {
        $male = $this->makeUser(User::GENDER_MALE, '阿宁');

        $this->expectException(ClBusinessException::class);
        $this->service->changeGender($this->operator, $male->id, User::GENDER_MALE, '没变化');
    }

    // ---------- 导出 ----------

    public function testExportNeverCarriesPasswordOrIdCard(): void
    {
        $this->makeUser(User::GENDER_MALE, '阿宁', coins: 100);
        $this->makeUser(User::GENDER_FEMALE, '晚晴', points: 6264, earned: 1244);

        $maleTable = $this->service->exportRows(['gender' => User::GENDER_MALE], false);
        $femaleTable = $this->service->exportRows(['gender' => User::GENDER_FEMALE], true);

        foreach ([$maleTable, $femaleTable] as $table) {
            $joined = implode(',', $table['headers']);
            self::assertStringNotContainsString('密码', $joined);
            self::assertStringNotContainsString('身份证', $joined);
            self::assertCount(1, $table['rows']);
        }

        self::assertContains('金币余额', $maleTable['headers']);
        self::assertContains('累计充值', $maleTable['headers']);
        self::assertContains('累计收益', $femaleTable['headers']);
        self::assertContains('实名状态', $femaleTable['headers']);
        self::assertSame(['阿宁'], [$maleTable['rows'][0][3]]);
    }

    // ---------- 工具 ----------

    private function makeUser(int $gender, string $nickname, int $coins = 0, int $points = 0, int $earned = 0, int $vipDays = 0): User
    {
        $phone = '13900000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $nickname,
            'avatar' => 'https://cdn.example.com/' . $this->sequence . '.jpg',
            'city' => '上海',
            'level' => 1,
            'total_earned_points' => $earned,
            'real_name_status' => User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
            'source' => 1,
            'vip_expire_at' => $vipDays > 0 ? date('Y-m-d H:i:s', time() + $vipDays * 86400) : null,
        ]);

        CoinWallet::query()->create(['user_id' => $user->id, 'balance' => $coins, 'total_recharge' => $coins]);
        PointWallet::query()->create(['user_id' => $user->id, 'balance' => $points, 'frozen' => 0]);

        return $user;
    }

    private function clear(): void
    {
        AdminActionLog::query()->delete();
        VipOrder::query()->delete();
        VipPlan::query()->delete();
        Notice::query()->delete();
        UserPhoto::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
