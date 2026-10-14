<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Gift;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Permission\Menu;
use App\Model\Permission\Meta;
use App\Model\Permission\Role;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use HyperfTests\Feature\Admin\GetTokenTrait;
use HyperfTests\HttpTestCase;

/**
 * 运营配置的接口层（docs/07 第 3.15~3.23 节）。
 *
 * **`07` T7.7 的两条点名校验在第一个用例里**：
 * 「改配置后 App 接口立即生效」（改免费条数 → `/chat/enter` 的 fee 跟着变）
 * 和「`compliance.disclosure` 不能存空」。
 *
 * @internal
 * @coversNothing
 */
final class AdminConfigControllerTest extends HttpTestCase
{
    use GetTokenTrait;

    private int $sequence = 0;

    private string $originalFreeCount = '3';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->originalFreeCount = (string) make(AppConfigService::class)->get('chat.free_count_per_pair', 3);
    }

    protected function tearDown(): void
    {
        $this->clear();
        // 配置是全表共享的，改完必须还原（不然别的测试类会跟着变）
        Db::table('cl_config')->where('key', 'chat.free_count_per_pair')->update(['value' => $this->originalFreeCount]);
        make(AppConfigService::class)->forget();
        parent::tearDown();
    }

    /** **验收一**：后台把免费条数改成 0，App 那边的收费条立刻从「免费」变成要扣币。 */
    public function testChangingFreeCountTakesEffectOnAppFeeImmediately(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $male = $this->makeUser(User::GENDER_MALE, coins: 100);
        $female = $this->makeUser(User::GENDER_FEMALE);
        $appToken = $this->appTokenOf($male->account);

        // 还没聊过，前几条是免费的
        $before = $this->appGet($appToken, "/api/v1/chat/conversations/with/{$female->id}");
        self::assertSame(0, (int) $before['data']['fee']['next_price'], '免费期内不收费');
        self::assertSame('PAIR_FREE', (string) $before['data']['fee']['free_reason']);

        // 后台改成 0
        $save = $this->putJson('/admin/cl/config/billing', [
            'values' => ['chat.free_count_per_pair' => '0'],
        ], $token);
        self::assertSame(200, $save['code'], json_encode($save, JSON_UNESCAPED_UNICODE));

        // App 立刻按新规则算（服务端缓存被清了）
        $after = $this->appGet($appToken, "/api/v1/chat/conversations/with/{$female->id}");
        self::assertSame(
            (int) LevelConfig::query()->where('level', 1)->value('message_price'),
            (int) $after['data']['fee']['next_price'],
            '改成 0 条之后第一条就该按她的等级收费了',
        );
        self::assertNotSame('PAIR_FREE', (string) ($after['data']['fee']['free_reason'] ?? ''));
    }

    /** **验收二**：合规声明不能存空。 */
    public function testComplianceDisclosureCannotBeEmpty(): void
    {
        $token = $this->tokenOf($this->superAdmin());

        foreach (['', '   '] as $value) {
            $result = $this->putJson('/admin/cl/config/compliance', [
                'values' => ['compliance.disclosure' => $value],
            ], $token);

            self::assertSame(40001, $result['code'], '空合规声明必须被拒，实际：' . json_encode($result, JSON_UNESCAPED_UNICODE));
        }

        self::assertNotSame(
            '',
            (string) Db::table('cl_config')->where('key', 'compliance.disclosure')->value('value'),
            '库里那条不能被改空',
        );
    }

    /** 客服没有运营配置的权限码，改计费那组要 403。 */
    public function testKefuCannotChangeBillingGroup(): void
    {
        $token = $this->tokenOf($this->roleAdmin('kefu', 'cl:operation:notice'));

        $result = $this->putJson('/admin/cl/config/billing', [
            'values' => ['coin.per_yuan' => '999'],
        ], $token);

        self::assertSame(403, $result['code'], '客服只有 cl:operation:notice，改计费要 403');
    }

    public function testGroupsEndpointListsGroups(): void
    {
        $token = $this->tokenOf($this->superAdmin());

        $result = $this->get('/admin/cl/config/groups', [], $this->bearer($token));

        self::assertSame(200, $result['code']);
        self::assertContains('billing', array_keys($result['data']['all']));
        self::assertContains('withdraw', $result['data']['billing'], '提现那组并进了计费页');
    }

    public function testLevelsCanBeReadAndSaved(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $levels = $this->get('/admin/cl/config/levels', [], $this->bearer($token))['data'];
        self::assertCount(5, $levels);

        $levels[0]['voice_price'] = 66;
        $save = $this->put('/admin/cl/config/levels', ['items' => $levels], $this->bearer($token));
        self::assertSame(200, $save['code'], json_encode($save, JSON_UNESCAPED_UNICODE));
        self::assertSame(66, (int) LevelConfig::query()->where('level', 1)->value('voice_price'));
    }

    /** 门槛不递增要被挡（校验在服务里，接口层只传上去）。 */
    public function testLevelsRejectNonIncreasingThreshold(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $levels = $this->get('/admin/cl/config/levels', [], $this->bearer($token))['data'];
        $levels[1]['threshold_points'] = 0;

        $save = $this->put('/admin/cl/config/levels', ['items' => $levels], $this->bearer($token));

        self::assertSame(40001, $save['code']);
    }

    public function testGiftCrudThroughApi(): void
    {
        $token = $this->tokenOf($this->superAdmin());

        $save = $this->post('/admin/cl/gift/save', [
            'name' => '小星星', 'icon' => '⭐', 'price_coins' => 20, 'sort' => 9, 'enabled' => 1,
        ], $this->bearer($token));
        self::assertSame(200, $save['code'], json_encode($save, JSON_UNESCAPED_UNICODE));

        $giftId = (int) Gift::query()->where('name', '小星星')->value('id');
        $list = $this->get('/admin/cl/gift/list', [], $this->bearer($token));
        self::assertSame(200, $list['code']);
        self::assertContains('小星星', array_column($list['data'], 'name'));

        $delete = $this->post('/admin/cl/gift/delete', ['ids' => [$giftId]], $this->bearer($token));
        self::assertSame(200, $delete['code']);
        self::assertNull(Gift::query()->whereKey($giftId)->first());
    }

    public function testNoticePublishAnnounceAndDirectSend(): void
    {
        $token = $this->tokenOf($this->superAdmin());
        $user = $this->makeUser(User::GENDER_FEMALE);

        // 全员公告
        $announce = $this->post('/admin/cl/notice/publish', ['title' => '维护通知', 'content' => '今晚 2 点维护'], $this->bearer($token));
        self::assertSame(200, $announce['code']);
        self::assertSame('announce', $announce['data']['mode']);
        self::assertSame(1, Db::table('cl_notice')->where('user_id', 0)->count());

        // 定向发送：不存在的 ID 跳过并报出来，不整个失败
        $direct = $this->post('/admin/cl/notice/publish', [
            'title' => '给你的一句话',
            'content' => '你好呀',
            'user_ids' => [$user->id, 999999],
        ], $this->bearer($token));
        self::assertSame(200, $direct['code']);
        self::assertSame(1, $direct['data']['sent']);
        self::assertSame([999999], $direct['data']['missing']);
        self::assertSame(1, Db::table('cl_notice')->where('user_id', $user->id)->count());

        $list = $this->get('/admin/cl/notice/list', [], $this->bearer($token));
        self::assertSame(200, $list['code']);
        self::assertSame(2, $list['data']['total'], '已发布列表只列后台发的这两条');
    }

    // ---------- 工具 ----------

    private function superAdmin(): AdminUser
    {
        $user = $this->adminUser();
        $user->roles()->sync(Role::query()->create(['name' => '超级管理员', 'code' => 'SuperAdmin']));

        return $user;
    }

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

    private function appTokenOf(string $account): string
    {
        $result = $this->client->post('/api/v1/auth/login', ['account' => $account, 'password' => 'abc12345']);
        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));

        return (string) $result['data']['access_token'];
    }

    private function appGet(string $appToken, string $url): array
    {
        return $this->client->get($url, [], ['Authorization' => 'Bearer ' . $appToken]);
    }

    /**
     * 走 JSON 发请求 —— **前端 axios 就是这么发的**。
     *
     * 不能用 `$this->put($url, ['values' => [...]])`：那是表单编码，而表单编码会把 key 里的
     * `.` 换成 `_`（`coin.per_yuan` → `coin_per_yuan`），配置项会被静默跳过（真踩过）。
     */
    private function putJson(string $url, array $body, string $token): array
    {
        $response = $this->client->request('PUT', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'json' => $body,
        ]);

        // `request()` 给的是原始响应（没走 JSON 解包），自己解一下
        return (array) json_decode((string) $response->getBody(), true);
    }

    private function makeUser(int $gender, int $coins = 0): User
    {
        $phone = '13100000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $gender === User::GENDER_MALE ? '阿宁' : '晚晴',
            'avatar' => 'https://cdn.example.com/a.jpg',
            'level' => 1,
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id, 'balance' => $coins]);
        PointWallet::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function clear(): void
    {
        Gift::query()->where('name', '小星星')->delete();
        Conversation::query()->delete();
        Db::table('cl_message')->delete();
        Db::table('cl_notice')->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
        Db::table('role_belongs_menu')->delete();
        Db::table('user_belongs_role')->delete();
        AdminUser::query()->delete();
        Role::query()->delete();
        Menu::query()->where('name', 'cl:operation:notice')->delete();
    }
}
