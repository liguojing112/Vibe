<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Cl\BatchAccount;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Permission\Menu;
use App\Model\Permission\Meta;
use App\Model\Permission\Role;
use App\Model\Permission\User as AdminUser;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use HyperfTests\Feature\Admin\GetTokenTrait;
use HyperfTests\HttpTestCase;

/**
 * 批量开号的接口层（docs/07 第 3.3 节）。
 *
 * 这里只验接口接对了没有、权限码挡不挡得住 —— 发号规则本身在 `AdminBatchServiceTest` 里。
 *
 * @internal
 * @coversNothing
 */
final class AdminBatchControllerTest extends HttpTestCase
{
    use GetTokenTrait;

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

    public function testCreateReturnsPlaintextOnceAndPersistsAccounts(): void
    {
        $token = $this->tokenOf($this->superAdmin());

        $result = $this->post('/admin/cl/user/batch-create', [
            'gender' => User::GENDER_FEMALE,
            'count' => 3,
            'prefix' => 'F',
            'remark' => '走查用',
        ], $this->bearer($token));

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertCount(3, $result['data']['accounts']);

        $account = $result['data']['accounts'][0];
        self::assertSame(8, strlen($account['password']), '明文密码只在这一次响应里出现');
        self::assertSame('用户' . substr($account['account'], -4), $account['nickname']);

        /** @var User $user */
        $user = User::query()->where('account', $account['account'])->firstOrFail();
        self::assertTrue(password_verify($account['password'], $user->password));
        self::assertSame('', $user->avatar);
        self::assertSame($result['data']['batch_no'], $user->batch_no);
    }

    public function testStaffWithoutBatchPermissionIsForbidden(): void
    {
        // 客服有 cl:user:list，但**没有** cl:user:batch —— 开号是一次性动作，不该给客服
        $token = $this->tokenOf($this->limitedAdmin('cl:user:list'));

        $result = $this->post('/admin/cl/user/batch-create', [
            'gender' => User::GENDER_MALE,
            'count' => 1,
        ], $this->bearer($token));

        self::assertSame(403, $result['code']);
        self::assertSame(0, User::query()->count(), '被挡住时不能建出任何账号');
    }

    public function testBatchListAndExportFlag(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $created = $this->post('/admin/cl/user/batch-create', ['gender' => 1, 'count' => 2], $this->bearer($token));
        $batchNo = $created['data']['batch_no'];

        $list = $this->get('/admin/cl/user/batch/list?page=1&page_size=10', [], $this->bearer($token));
        self::assertSame(200, $list['code']);
        self::assertSame(1, $list['data']['total']);
        self::assertSame($batchNo, $list['data']['list'][0]['batch_no']);
        self::assertFalse($list['data']['list'][0]['exported']);

        $marked = $this->post("/admin/cl/user/batch/{$batchNo}/export", [], $this->bearer($token));
        self::assertSame(200, $marked['code']);
        self::assertTrue((bool) BatchAccount::query()->where('batch_no', $batchNo)->value('exported'));
    }

    public function testValidationRejectsTooManyAccounts(): void
    {
        $token = $this->tokenOf($this->superAdmin());

        $result = $this->post('/admin/cl/user/batch-create', ['gender' => 1, 'count' => 500], $this->bearer($token));

        self::assertSame(422, $result['code']);
    }

    // ---------- 工具 ----------

    private function superAdmin(): AdminUser
    {
        $user = $this->adminUser();
        $user->roles()->sync(Role::query()->create(['name' => '超级管理员', 'code' => 'SuperAdmin']));

        return $user;
    }

    private function limitedAdmin(string ...$codes): AdminUser
    {
        $user = $this->adminUser();
        $role = Role::query()->create(['name' => '客服', 'code' => 'ClKefu' . Str::random(4)]);

        $ids = [];
        foreach ($codes as $code) {
            $ids[] = (int) Menu::query()->create([
                'parent_id' => 0,
                'name' => $code,
                'meta' => new Meta(['title' => $code, 'type' => 'B']),
            ])->id;
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

    private function clear(): void
    {
        BatchAccount::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
        Db::table('role_belongs_menu')->delete();
        Db::table('user_belongs_role')->delete();
        AdminUser::query()->delete();
        Role::query()->delete();
        Menu::query()->whereIn('name', ['cl:user:list', 'cl:user:batch'])->delete();
    }
}
