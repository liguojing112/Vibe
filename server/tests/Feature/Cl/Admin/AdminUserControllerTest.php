<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Cl\VipPlan;
use App\Model\Permission\Menu;
use App\Model\Permission\Meta;
use App\Model\Permission\Role;
use App\Model\Permission\User as AdminUser;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use HyperfTests\Feature\Admin\GetTokenTrait;
use HyperfTests\HttpTestCase;
use OpenSpout\Reader\XLSX\Reader;

/**
 * 后台用户管理的接口层（docs/07 第 3.2、4 节）。
 *
 * 两件事在这里才验得到：
 * - **权限码真的在挡人**（超管畅通，只有 `cl:user:list` 的账号调封禁接口要 403）；
 * - **导出真的是一个能打开的 xlsx**，且里面没有密码和身份证。
 *
 * @internal
 * @coversNothing
 */
final class AdminUserControllerTest extends HttpTestCase
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

    public function testSuperAdminCanListBothGenders(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $male = $this->makeAppUser(User::GENDER_MALE, '阿宁');
        $female = $this->makeAppUser(User::GENDER_FEMALE, '晚晴');

        $maleResult = $this->get('/admin/cl/user/list?gender=1', [], $this->bearer($token));
        $femaleResult = $this->get('/admin/cl/user/list?gender=2', [], $this->bearer($token));

        self::assertSame(200, $maleResult['code'], json_encode($maleResult, JSON_UNESCAPED_UNICODE));
        self::assertSame($male->id, (int) $maleResult['data']['list'][0]['id']);
        self::assertSame($female->id, (int) $femaleResult['data']['list'][0]['id']);
    }

    public function testDetailReturnsDrawerPayload(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $female = $this->makeAppUser(User::GENDER_FEMALE, '晚晴', points: 6264);

        $result = $this->get('/admin/cl/user/' . $female->id, [], $this->bearer($token));

        self::assertSame(200, $result['code']);
        self::assertSame('晚晴', $result['data']['user']['nickname']);
        self::assertSame(6264, $result['data']['point']['balance']);
        self::assertArrayHasKey('bans', $result['data']);
        self::assertArrayHasKey('logs', $result['data']);
    }

    public function testBanThenUserTokenIsRejected(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $male = $this->makeAppUser(User::GENDER_MALE, '阿宁');
        $appToken = $this->appTokenOf($male);

        $ok = $this->get('/api/v1/user/me', [], ['Authorization' => 'Bearer ' . $appToken]);
        self::assertSame(200, $ok['code'], '封之前 App 接口是通的');

        $ban = $this->post('/admin/cl/user/' . $male->id . '/ban', ['reason' => '发布违规内容', 'days' => 7], $this->bearer($token));
        self::assertSame(200, $ban['code'], json_encode($ban, JSON_UNESCAPED_UNICODE));

        $after = $this->get('/api/v1/user/me', [], ['Authorization' => 'Bearer ' . $appToken]);
        self::assertSame(401, $after['code'], '封完之后手上的 token 应该当场失效');
    }

    public function testValidationFailureReturns422(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $male = $this->makeAppUser(User::GENDER_MALE, '阿宁');

        // 封禁必须填原因
        $result = $this->post('/admin/cl/user/' . $male->id . '/ban', ['days' => 7], $this->bearer($token));

        self::assertSame(422, $result['code']);
    }

    public function testStaffWithoutBanPermissionGetsForbidden(): void
    {
        $token = $this->tokenOf($this->limitedAdmin('cl:user:list'));
        $male = $this->makeAppUser(User::GENDER_MALE, '阿宁');

        // 有 list，所以列表能看
        $list = $this->get('/admin/cl/user/list?gender=1', [], $this->bearer($token));
        self::assertSame(200, $list['code']);

        // 没有 ban，封禁要被挡
        $ban = $this->post('/admin/cl/user/' . $male->id . '/ban', ['reason' => '试试', 'days' => 1], $this->bearer($token));
        self::assertSame(403, $ban['code'], '客服没有 cl:user:ban，必须 403');
        self::assertSame(User::STATUS_NORMAL, $male->refresh()->status, '被挡住的时候状态不能变');
    }

    public function testExportReturnsReadableXlsxWithoutSecrets(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $this->makeAppUser(User::GENDER_MALE, '阿宁', coins: 100);

        // 下载是二进制响应，测试客户端的 `get()` 会按 JSON 解、解不出来返回 null，所以走 sendRequest
        $response = $this->client->sendRequest(
            $this->client->initRequest('GET', '/admin/cl/user/export', [
                'query' => ['gender' => 1],
                'headers' => $this->bearer($token),
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('attachment', $response->getHeaderLine('content-disposition'));
        self::assertStringContainsString('.xlsx', $response->getHeaderLine('content-disposition'));

        // `Response::download()` 挂的是 Swoole 的文件流（body 里是文件路径，真跑的时候由
        // sendfile 把文件发出去），所以这里把路径取回来、直接从盘上读内容验
        $path = (string) $response->getBody();
        self::assertFileExists($path);

        $rows = $this->readXlsx($path);
        @unlink($path);

        self::assertNotEmpty($rows);
        $header = $rows[0];
        self::assertContains('金币余额', $header);
        self::assertContains('累计充值', $header);
        foreach ($header as $column) {
            self::assertStringNotContainsString('密码', $column);
            self::assertStringNotContainsString('身份证', $column);
        }
        self::assertSame('阿宁', $rows[1][3], '依次是 ID / 账号 / 手机号 / 昵称，昵称在第 4 列');
    }

    // ---------- 工具 ----------

    /** @return array<int,array<int,string>> */
    private function readXlsx(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(static fn ($value): string => (string) ($value ?? ''), $row->toArray());
            }
        }
        $reader->close();

        return $rows;
    }

    private function superAdmin(): AdminUser
    {
        $user = $this->adminUser();
        $role = Role::query()->create(['name' => '超级管理员', 'code' => 'SuperAdmin']);
        $user->roles()->sync($role);

        return $user;
    }

    /** 不是超管、只给几个权限码的账号（用来验 403）。 */
    private function limitedAdmin(string ...$codes): AdminUser
    {
        $user = $this->adminUser();
        $role = Role::query()->create(['name' => '客服', 'code' => 'ClKefu' . Str::random(4)]);

        $ids = [];
        foreach ($codes as $code) {
            $menu = Menu::query()->create([
                'parent_id' => 0,
                'name' => $code,
                'meta' => new Meta(['title' => $code, 'type' => 'B']),
            ]);
            $ids[] = (int) $menu->id;
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

    private function appTokenOf(User $user): string
    {
        $result = $this->client->post('/api/v1/auth/login', [
            'account' => $user->account,
            'password' => 'abc12345',
        ]);
        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));

        return (string) $result['data']['access_token'];
    }

    /** @return array<string,string> */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    private function makeAppUser(int $gender, string $nickname, int $coins = 0, int $points = 0): User
    {
        $phone = '13700000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $nickname,
            'avatar' => 'https://cdn.example.com/a.jpg',
            'city' => '上海',
            'level' => 1,
            'real_name_status' => User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id, 'balance' => $coins, 'total_recharge' => $coins]);
        PointWallet::query()->create(['user_id' => $user->id, 'balance' => $points, 'frozen' => 0]);

        return $user;
    }

    private function clear(): void
    {
        VipPlan::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
        Db::table('role_belongs_menu')->delete();
        Db::table('user_belongs_role')->delete();
        AdminUser::query()->delete();
        Role::query()->delete();
        Menu::query()->whereIn('name', ['cl:user:list', 'cl:user:ban'])->delete();
    }
}
