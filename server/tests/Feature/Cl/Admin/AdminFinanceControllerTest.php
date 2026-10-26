<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\RechargeOrder;
use App\Model\Cl\User;
use App\Model\Cl\WithdrawOrder;
use App\Model\Permission\Menu;
use App\Model\Permission\Meta;
use App\Model\Permission\Role;
use App\Model\Permission\User as AdminUser;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use HyperfTests\Feature\Admin\GetTokenTrait;
use HyperfTests\HttpTestCase;

/**
 * 资金管理的接口层与权限（docs/07 第 3.10~3.14 节、第 4 节）。
 *
 * **`07` 第 4 节点名要求的两个 403 就在这里**：
 * 客服调用提现审核接口返回 403、财务调用充值接口返回 403 —— 这是「钱不能一个人说了算」那条规矩的落点。
 *
 * @internal
 * @coversNothing
 */
final class AdminFinanceControllerTest extends HttpTestCase
{
    use GetTokenTrait;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
    }

    protected function tearDown(): void
    {
        $this->clear();
        parent::tearDown();
    }

    /** `07` 第 4 节要求的第一个 403：客服不能审提现。 */
    public function testKefuCanRechargeButNotAuditWithdraw(): void
    {
        $token = $this->tokenOf($this->roleAdmin('kefu', 'cl:finance:recharge', 'cl:withdraw:list'));
        $male = $this->makeUser(User::GENDER_MALE);
        $order = $this->makeWithdrawOrder($this->makeUser(User::GENDER_FEMALE));

        // 充值可以
        $recharge = $this->post('/admin/cl/finance/recharge', [
            'user_id' => $male->id,
            'coins' => 200,
            'amount_fen' => 2000,
            'pay_channel' => 'WECHAT',
        ], $this->bearer($token));
        self::assertSame(200, $recharge['code'], json_encode($recharge, JSON_UNESCAPED_UNICODE));
        self::assertSame(200, (int) CoinWallet::query()->where('user_id', $male->id)->value('balance'));

        // 提现审核不行
        $approve = $this->post("/admin/cl/withdraw/{$order->id}/approve", [], $this->bearer($token));
        self::assertSame(403, $approve['code'], '客服没有 cl:withdraw:approve，必须 403');
        self::assertSame(WithdrawOrder::STATUS_PENDING, (int) $order->refresh()->status, '被挡住时状态不能变');
    }

    /** `07` 第 4 节要求的第二个 403：财务不能充值。 */
    public function testCaiwuCanAuditWithdrawButNotRecharge(): void
    {
        $token = $this->tokenOf($this->roleAdmin('caiwu', 'cl:withdraw:approve', 'cl:finance:recharge-list'));
        $male = $this->makeUser(User::GENDER_MALE);
        $order = $this->makeWithdrawOrder($this->makeUser(User::GENDER_FEMALE));

        // 审核提现可以
        $approve = $this->post("/admin/cl/withdraw/{$order->id}/approve", [], $this->bearer($token));
        self::assertSame(200, $approve['code'], json_encode($approve, JSON_UNESCAPED_UNICODE));
        self::assertSame(WithdrawOrder::STATUS_APPROVED, (int) $order->refresh()->status);

        // 充值不行
        $recharge = $this->post('/admin/cl/finance/recharge', [
            'user_id' => $male->id,
            'coins' => 200,
            'pay_channel' => 'WECHAT',
        ], $this->bearer($token));
        self::assertSame(403, $recharge['code'], '财务没有 cl:finance:recharge，必须 403');
        self::assertSame(0, RechargeOrder::query()->count(), '被挡住时不能建出充值单');
        self::assertSame(0, (int) CoinWallet::query()->where('user_id', $male->id)->value('balance'));
    }

    /** 三个动作都能从接口走通（状态机本身在 T6.3 的 `WithdrawServiceTest` 里测）。 */
    public function testWithdrawThreeActionsThroughApi(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $first = $this->makeWithdrawOrder($this->makeUser(User::GENDER_FEMALE));
        $second = $this->makeWithdrawOrder($this->makeUser(User::GENDER_FEMALE));

        self::assertSame(200, $this->post("/admin/cl/withdraw/{$first->id}/approve", [], $this->bearer($token))['code']);
        self::assertSame(WithdrawOrder::STATUS_APPROVED, (int) $first->refresh()->status);

        // 驳回必须填原因
        $noReason = $this->post("/admin/cl/withdraw/{$second->id}/reject", [], $this->bearer($token));
        self::assertNotSame(200, $noReason['code'], '不填原因不能驳回');

        self::assertSame(200, $this->post("/admin/cl/withdraw/{$second->id}/reject", ['reason' => '账号信息不符'], $this->bearer($token))['code']);
        self::assertSame(WithdrawOrder::STATUS_REJECTED, (int) $second->refresh()->status);

        // 标记已打款必须填打款流水号
        $noProof = $this->post("/admin/cl/withdraw/{$first->id}/paid", [], $this->bearer($token));
        self::assertNotSame(200, $noProof['code'], '不打流水号不能标已打款');

        self::assertSame(200, $this->post("/admin/cl/withdraw/{$first->id}/paid", ['pay_proof' => 'WX20261014001'], $this->bearer($token))['code']);
        self::assertSame(WithdrawOrder::STATUS_PAID, (int) $first->refresh()->status);
        self::assertSame('WX20261014001', (string) $first->pay_proof);
    }

    public function testWithdrawListCarriesTotalsAndRealName(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $this->makeWithdrawOrder($this->makeUser(User::GENDER_FEMALE), amountFen: 5000, actualFen: 5000);

        $result = $this->get('/admin/cl/withdraw/list?status=0', [], $this->bearer($token));

        self::assertSame(200, $result['code']);
        self::assertSame(1, $result['data']['total']);
        self::assertSame(5000, $result['data']['amount_fen'], '底部合计');
        self::assertSame('待审核', $result['data']['list'][0]['status_text']);
        self::assertSame('支付宝', $result['data']['list'][0]['account_type_text']);
    }

    /** 平台收入：不传日期默认最近 30 天，接口要能出数据（至少不报错）。 */
    public function testIncomeEndpointReturnsDailyRows(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $today = date('Y-m-d');
        Db::table('cl_platform_income')->insert([
            'source' => 'MESSAGE',
            'ref_id' => 1,
            'payer_id' => 1,
            'receiver_id' => 2,
            'coins' => 100,
            'receiver_points' => 40,
            'income_date' => $today,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get('/admin/cl/finance/income', [], $this->bearer($token));

        self::assertSame(200, $result['code']);
        self::assertSame($today, $result['data']['list'][0]['date']);
        self::assertSame(100, (int) $result['data']['list'][0]['coins']);
    }

    /** 列表「加款」：客服有 cl:finance:grant，男号加金币、女号加积分；财务没有该权限。 */
    public function testKefuCanGrantCoinsAndPointsButCaiwuCannot(): void
    {
        $kefu = $this->tokenOf($this->roleAdmin('kefu', 'cl:finance:grant'));
        $male = $this->makeUser(User::GENDER_MALE);
        $female = $this->makeUser(User::GENDER_FEMALE);

        $coins = $this->post('/admin/cl/finance/grant', [
            'user_id' => $male->id,
            'amount' => 100,
            'remark' => '测试加款',
        ], $this->bearer($kefu));
        self::assertSame(200, $coins['code'], json_encode($coins, JSON_UNESCAPED_UNICODE));
        self::assertSame(100, (int) CoinWallet::query()->where('user_id', $male->id)->value('balance'));

        $points = $this->post('/admin/cl/finance/grant', [
            'user_id' => $female->id,
            'amount' => 200,
            'remark' => '测试加款',
        ], $this->bearer($kefu));
        self::assertSame(200, $points['code'], json_encode($points, JSON_UNESCAPED_UNICODE));
        self::assertSame(200, (int) PointWallet::query()->where('user_id', $female->id)->value('balance'));

        $caiwu = $this->tokenOf($this->roleAdmin('caiwu', 'cl:finance:recharge-list'));
        $denied = $this->post('/admin/cl/finance/grant', [
            'user_id' => $male->id,
            'amount' => 100,
            'remark' => '测试',
        ], $this->bearer($caiwu));
        self::assertSame(403, $denied['code'], '财务没有 cl:finance:grant，必须 403');
    }

    // ---------- 工具 ----------

    private function superAdmin(): AdminUser
    {
        $user = $this->adminUser();
        $user->roles()->sync(Role::query()->create(['name' => '超级管理员', 'code' => 'SuperAdmin']));

        return $user;
    }

    /** 建一个只有指定权限码的账号（用来验 403）。 */
    private function roleAdmin(string $name, string ...$codes): AdminUser
    {
        $user = $this->adminUser();
        $role = Role::query()->create(['name' => $name, 'code' => ucfirst($name) . Str::random(4)]);

        $ids = [];
        foreach ($codes as $code) {
            $ids[] = (int) Menu::query()->firstOrCreate(
                ['name' => $code],
                ['parent_id' => 0, 'meta' => new Meta(['title' => $code, 'type' => 'B'])],
            )->id;
        }
        $role->menus()->sync($ids);
        $user->roles()->sync($role);

        return $user;
    }

    private function adminUser(): AdminUser
    {
        return AdminUser::create([
            'username' => 'admin' . Str::random(6),
            'password' => '123456',
            'user_type' => '100',
            'status' => 1,
        ]);
    }

    private function tokenOf(AdminUser $user): string
    {
        return $this->getToken($user);
    }

    /** @return array<string,string> */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function makeWithdrawOrder(User $user, int $amountFen = 5000, int $actualFen = 5000): WithdrawOrder
    {
        // 申请提现时会把积分从可用挪到冻结（docs/06 第 9.1 节第 7 步）；
        // 这里的单据是直接造的，所以要把冻结那部分补上，不然驳回/打款时「冻结不足」
        $wallet = PointWallet::query()->where('user_id', $user->id)->firstOrFail();
        $wallet->frozen = $amountFen / 10;
        $wallet->save();

        /** @var WithdrawOrder $order */
        $order = WithdrawOrder::query()->create([
            'order_no' => 'W' . date('YmdHis') . ++$this->sequence,
            'user_id' => $user->id,
            'amount_fen' => $amountFen,
            'points' => $amountFen / 10,
            'fee_fen' => 0,
            'actual_fen' => $actualFen,
            'account_type' => 'ALIPAY',
            'account_no' => 'user@example.com',
            'account_name' => '晚晴',
            'status' => WithdrawOrder::STATUS_PENDING,
        ]);

        return $order;
    }

    private function makeUser(int $gender): User
    {
        $phone = '13200000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $gender === User::GENDER_MALE ? '阿宁' : '晚晴',
            'avatar' => 'https://cdn.example.com/a.jpg',
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id]);
        PointWallet::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function clear(): void
    {
        RechargeOrder::query()->delete();
        WithdrawOrder::query()->delete();
        Db::table('cl_platform_income')->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
        Db::table('role_belongs_menu')->delete();
        Db::table('user_belongs_role')->delete();
        AdminUser::query()->delete();
        Role::query()->delete();
        Menu::query()->whereIn('name', [
            'cl:finance:recharge', 'cl:withdraw:list', 'cl:withdraw:approve',
            'cl:finance:recharge-list',
        ])->delete();
    }
}
