<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Permission\Menu;
use App\Model\Permission\Meta;
use App\Model\Permission\Role;
use App\Model\Permission\User as AdminUser;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use HyperfTests\Feature\Admin\GetTokenTrait;
use HyperfTests\HttpTestCase;

/**
 * 数据看板的接口与权限（docs/07 第 3.1 节、第 4 节）。
 *
 * 看板是「四个角色都能看」的那个页面，所以权限码只有 `cl:dashboard` 一个；
 * 但**没有这个权限码的账号必须被挡住**，不能因为它是首页就默认放行。
 *
 * @internal
 * @coversNothing
 */
final class AdminDashboardControllerTest extends HttpTestCase
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

    public function testOverviewReturnsCardsAndThirtyDayChart(): void
    {
        $result = $this->get('/admin/cl/dashboard/overview', [], $this->bearer($this->tokenOf($this->roleAdmin('kefu', 'cl:dashboard'))));

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertSame(date('Y-m-d'), $result['data']['date']);
        self::assertSame(30, $result['data']['days']);

        // 卡片一个不能少（前端按这些 key 渲染）
        foreach ([
            'new_male', 'new_female', 'active_users', 'paid_messages', 'gift_coins',
            'recharge_coins', 'recharge_amount_fen', 'platform_income_fen',
            'pending_profile', 'pending_realname', 'pending_reports', 'pending_messages',
            'pending_withdraw_count', 'pending_withdraw_fen',
        ] as $key) {
            self::assertArrayHasKey($key, $result['data']['cards'], "卡片缺了 {$key}");
        }

        self::assertCount(30, $result['data']['chart']['dates']);
        self::assertCount(30, $result['data']['chart']['new_male']);
        self::assertCount(30, $result['data']['chart']['platform_income_fen']);
    }

    /** 没有 `cl:dashboard` 的账号必须 403 —— 首页也不能例外。 */
    public function testWithoutDashboardPermissionItIsForbidden(): void
    {
        $result = $this->get('/admin/cl/dashboard/overview', [], $this->bearer($this->tokenOf($this->roleAdmin('other', 'cl:user:list'))));

        self::assertSame(403, $result['code'], '没有 cl:dashboard 必须 403');
    }

    private function roleAdmin(string $name, string ...$codes): AdminUser
    {
        /** @var AdminUser $user */
        $user = AdminUser::create([
            'username' => 'dl' . Str::random(6),
            'password' => '123456',
            'user_type' => '100',
            'status' => 1,
        ]);

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

    /** @return array<string,string> */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function tokenOf(AdminUser $user): string
    {
        return $this->getToken($user);
    }

    private function clear(): void
    {
        Db::table('role_belongs_menu')->delete();
        Db::table('user_belongs_role')->delete();
        AdminUser::query()->delete();
        Role::query()->delete();
        Menu::query()->whereIn('name', ['cl:dashboard', 'cl:user:list'])->delete();
    }
}
