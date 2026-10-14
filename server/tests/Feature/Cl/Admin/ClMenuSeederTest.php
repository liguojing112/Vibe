<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Permission\Menu;
use App\Model\Permission\Role;
use App\Model\Permission\User;
use Hyperf\DbConnection\Db;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 「畅撩交友」后台菜单与角色（docs/07 第 1、4 节）。
 *
 * 盯的是**权限边界**，不是菜单长什么样：给某个角色多挂一个菜单，
 * 后台页面上是看不见的（前端只是按权限码隐藏按钮），只有接口那一层的
 * `#[Permission]` 会拦；反过来少挂一个，客服就会莫名其妙点不到自己的功能。
 *
 * 种子文件在 `databases/seeders/`（全局命名空间，没进 composer autoload），
 * 所以这里手动 require 一次再跑 —— 和 `db:seed` 走的是同一份代码。
 *
 * @internal
 * @coversNothing
 */
final class ClMenuSeederTest extends TestCase
{
    use RunTestsInCoroutine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        require_once BASE_PATH . '/databases/seeders/zz_cl_menu_seeder_20261014.php';
        make(\ZzClMenuSeeder20261014::class)->run();
    }

    protected function tearDown(): void
    {
        $this->clear();
        parent::tearDown();
    }

    public function testThreeRolesAndAccountsAreCreated(): void
    {
        foreach ([['kefu', '客服', 'ClKefu'], ['caiwu', '财务', 'ClCaiwu'], ['shenhe', '审核员', 'ClShenhe']] as [$username, $nickname, $code]) {
            $role = Role::query()->where('code', $code)->first();
            self::assertNotNull($role, "角色 {$code} 没建出来");
            self::assertSame($nickname, $role->name);

            $user = User::query()->where('username', $username)->first();
            self::assertNotNull($user, "账号 {$username} 没建出来");
            self::assertTrue($user->verifyPassword('123456'), "{$username} 的密码不是 123456，走查时登不进去");
            self::assertSame($role->id, (int) $user->roles()->first()->id, "{$username} 没挂上 {$code}");
        }
    }

    public function testKefuSeesOwnBusinessButNotOthers(): void
    {
        $codes = $this->codesOf('ClKefu');

        // 客服能看的：用户、举报、充值、流水、发系统通知
        foreach (['cl:dashboard', 'cl:user:male', 'cl:user:female', 'cl:user:vip-grant', 'cl:report:list', 'cl:report:handle', 'cl:finance:recharge', 'cl:operation:notice'] as $code) {
            self::assertContains($code, $codes, "客服少了 {$code}");
        }

        // 客服不能看的
        foreach (['cl:user:batch', 'cl:user:level', 'cl:audit:profile', 'cl:audit:realname', 'cl:chat:sensitive', 'cl:withdraw:approve', 'cl:finance:income', 'cl:operation:billing'] as $code) {
            self::assertNotContains($code, $codes, "客服多了 {$code}");
        }
    }

    public function testCaiwuSeesMoneyButNotRechargeOrOperation(): void
    {
        $codes = $this->codesOf('ClCaiwu');

        foreach (['cl:withdraw:list', 'cl:withdraw:approve', 'cl:withdraw:reject', 'cl:withdraw:paid', 'cl:finance:income', 'cl:finance:point-log'] as $code) {
            self::assertContains($code, $codes, "财务少了 {$code}");
        }

        foreach (['cl:finance:recharge', 'cl:operation:billing', 'cl:user:update', 'cl:user:ban', 'cl:audit:profile'] as $code) {
            self::assertNotContains($code, $codes, "财务多了 {$code}");
        }
    }

    public function testShenheSeesAuditAndChatButNotRealNamePlain(): void
    {
        $codes = $this->codesOf('ClShenhe');

        foreach (['cl:audit:profile', 'cl:audit:realname', 'cl:chat:conversation', 'cl:chat:pending', 'cl:chat:sensitive', 'cl:user:ban'] as $code) {
            self::assertContains($code, $codes, "审核员少了 {$code}");
        }

        // 身份证明文只有超管能看
        foreach (['cl:audit:realname-plain', 'cl:user:level', 'cl:finance:recharge', 'cl:withdraw:approve', 'cl:operation:notice'] as $code) {
            self::assertNotContains($code, $codes, "审核员多了 {$code}");
        }
    }

    /**
     * 父级分组必须跟着子菜单一起给 —— 少了的话 `CurrentUser::filterCurrentUser()`
     * 建不出树，页面会散在侧边栏顶层。
     */
    public function testEveryRoleKeepsItsAncestors(): void
    {
        foreach (['ClKefu' => 'cl:finance', 'ClCaiwu' => 'cl:finance', 'ClShenhe' => 'cl:chat'] as $roleCode => $group) {
            $codes = $this->codesOf($roleCode);
            self::assertContains('cl', $codes, "{$roleCode} 少了根菜单");
            self::assertContains($group, $codes, "{$roleCode} 少了分组 {$group}");
        }
    }

    /** 权限码靠 `menu.name` 唯一标识，重名会让「谁有权限」变得含糊。 */
    public function testPermissionCodesAreUnique(): void
    {
        $names = Menu::query()->where('name', 'like', 'cl%')->pluck('name')->toArray();

        self::assertSame(count($names), count(array_unique($names)), '有重名的菜单/权限码');
    }

    /** @return string[] */
    private function codesOf(string $roleCode): array
    {
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        return $role->menus()->pluck('name')->toArray();
    }

    private function clear(): void
    {
        $names = Menu::query()->where('name', 'like', 'cl%')->pluck('id')->toArray();
        if ($names !== []) {
            Db::table('role_belongs_menu')->whereIn('menu_id', $names)->delete();
        }

        $roles = Role::query()->where('code', 'like', 'Cl%')->pluck('id')->toArray();
        if ($roles !== []) {
            Db::table('role_belongs_menu')->whereIn('role_id', $roles)->delete();
            Db::table('user_belongs_role')->whereIn('role_id', $roles)->delete();
            Db::table('user')->whereIn('username', ['kefu', 'caiwu', 'shenhe'])->delete();
            Role::query()->whereIn('id', $roles)->delete();
        }

        Db::table('menu')->where('name', 'like', 'cl%')->delete();
    }
}
