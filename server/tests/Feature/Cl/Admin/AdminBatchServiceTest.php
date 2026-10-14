<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\BatchAccount;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminBatchService;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Discover\DiscoverService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 批量开号（docs/07 第 3.3 节、docs/04 第 7 节）。
 *
 * 四条验收（docs/08 T7.3）：生成数量正确、账号唯一、**明文只能取一次**、
 * **女号无头像不出现在发现页**。
 *
 * @internal
 * @coversNothing
 */
final class AdminBatchServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminBatchService $service;

    private AdminUser $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminBatchService::class);
        $this->operator = AdminUser::create(['username' => 'admin', 'password' => '123456', 'nickname' => '超管']);

        // 发现页要读启动配置
        $config = make(AppConfigService::class);
        $config->set('feature.call_enabled', 0);
        $config->set('app.version', '1.0.0');
    }

    protected function tearDown(): void
    {
        $this->clear();
        AdminUser::query()->where('username', 'admin')->delete();
        parent::tearDown();
    }

    public function testCreatesExactCountWithWellFormedUniqueAccounts(): void
    {
        $result = $this->service->create($this->operator, User::GENDER_FEMALE, 5, '', '第一批女号');

        self::assertSame(5, $result['count']);
        self::assertCount(5, $result['accounts']);

        $accounts = array_column($result['accounts'], 'account');
        self::assertSame($accounts, array_unique($accounts), '同一批里账号不能重复');
        foreach ($accounts as $account) {
            self::assertMatchesRegularExpression('/^F\d{8}$/', $account, '账号规则是「前缀 + 8 位数字」');
        }

        self::assertSame(5, User::query()->where('batch_no', $result['batch_no'])->count());
        self::assertSame(1, BatchAccount::query()->where('batch_no', $result['batch_no'])->count());
    }

    /** 再开一批要接着上一批的号段往下发，不能又从头来（不然就撞唯一索引了）。 */
    public function testSecondBatchContinuesTheNumberRange(): void
    {
        $first = $this->service->create($this->operator, User::GENDER_MALE, 3, 'M', '');
        $second = $this->service->create($this->operator, User::GENDER_MALE, 2, 'M', '');

        $firstNumbers = array_map(static fn (array $row): int => (int) substr($row['account'], 1), $first['accounts']);
        $secondNumbers = array_map(static fn (array $row): int => (int) substr($row['account'], 1), $second['accounts']);

        self::assertSame(max($firstNumbers) + 1, min($secondNumbers), '第二批要接着第一批的号往下发');
        self::assertSame(
            5,
            User::query()->whereIn('account', [...array_column($first['accounts'], 'account'), ...array_column($second['accounts'], 'account')])->count(),
        );
    }

    public function testDefaultProfileHasNoAvatarAndRecognisableNickname(): void
    {
        $result = $this->service->create($this->operator, User::GENDER_FEMALE, 1, 'F', '');

        /** @var User $user */
        $user = User::query()->where('account', $result['accounts'][0]['account'])->firstOrFail();
        self::assertSame('', $user->avatar, '批量号默认没有头像');
        self::assertSame('用户' . substr($user->account, -4), $user->nickname);
        self::assertSame(2, (int) $user->source, 'source = 2 表示后台开号');
        self::assertSame($result['batch_no'], $user->batch_no);
        self::assertSame(User::STATUS_NORMAL, $user->status);
        self::assertNull($user->phone, '批量号可以没有手机号');

        // 钱包也要跟着建，不然这批号收不了付费私信
        self::assertNotNull(CoinWallet::query()->where('user_id', $user->id)->first());
        self::assertNotNull(PointWallet::query()->where('user_id', $user->id)->first());
    }

    /** 明文只在响应里出现一次：库里留下的必须是 bcrypt，不是明文。 */
    public function testPlainPasswordIsReturnedOnceAndNeverStored(): void
    {
        $result = $this->service->create($this->operator, User::GENDER_FEMALE, 1, 'F', '');
        $plain = $result['accounts'][0]['password'];

        self::assertSame(8, strlen($plain));

        /** @var User $user */
        $user = User::query()->where('account', $result['accounts'][0]['account'])->firstOrFail();
        self::assertTrue(password_verify($plain, $user->password), '返回的明文要能对上库里的哈希');
        self::assertStringNotContainsString($plain, (string) $user->password, '库里不能出现明文');

        // 批次表里也只有一个标记位，没有任何密码字段
        $batch = BatchAccount::query()->where('batch_no', $result['batch_no'])->firstOrFail();
        self::assertSame(0, (int) $batch->exported);
    }

    /** 女号是给真人用的号：**没有头像就不该出现在发现页**（docs/04 第 7 节）。 */
    public function testBatchFemaleWithoutAvatarDoesNotShowUpInDiscover(): void
    {
        $batch = $this->service->create($this->operator, User::GENDER_FEMALE, 2, 'F', '');
        $male = $this->makeMaleViewer();

        $list = make(DiscoverService::class)->list($male, ['tab' => 'recommend'], 1);
        $ids = array_column($list['list'], 'id');

        foreach (User::query()->where('batch_no', $batch['batch_no'])->pluck('id') as $id) {
            self::assertNotContains((int) $id, array_map('intval', $ids), '没头像的批量号不该出现在发现页');
        }

        // 传了头像之后就出现了（说明挡的是头像，不是别的什么）
        User::query()->where('batch_no', $batch['batch_no'])->update(['avatar' => 'https://cdn.example.com/f.jpg']);
        $after = make(DiscoverService::class)->list($male, ['tab' => 'recommend'], 1);
        self::assertContains(
            (int) User::query()->where('batch_no', $batch['batch_no'])->value('id'),
            array_map('intval', array_column($after['list'], 'id')),
        );
    }

    public function testBatchListCarriesOperatorName(): void
    {
        $this->service->create($this->operator, User::GENDER_MALE, 1, 'M', '备注一下');

        $result = $this->service->batches(1, 20);

        self::assertSame(1, $result['total']);
        self::assertSame('admin', $result['list'][0]['operator_name']);
        self::assertSame('备注一下', $result['list'][0]['remark']);
        self::assertFalse($result['list'][0]['exported']);
    }

    public function testMarkExportedOnlyFlipsTheFlag(): void
    {
        $batch = $this->service->create($this->operator, User::GENDER_MALE, 1, 'M', '');

        $this->service->markExported($batch['batch_no']);

        self::assertTrue((bool) BatchAccount::query()->where('batch_no', $batch['batch_no'])->value('exported'));
        self::assertTrue($this->service->batches(1, 20)['list'][0]['exported']);
    }

    public function testMarkExportedRejectsUnknownBatch(): void
    {
        $this->expectException(ClBusinessException::class);
        $this->service->markExported('B-不存在');
    }

    public function testRejectsBadCountAndPrefix(): void
    {
        foreach ([[0, 'M'], [201, 'M'], [1, '带空格 的'], [1, '太长的前缀啊啊啊']] as [$count, $prefix]) {
            try {
                $this->service->create($this->operator, User::GENDER_MALE, $count, $prefix, '');
                self::fail("count={$count} prefix={$prefix} 应该被挡住");
            } catch (ClBusinessException) {
                self::assertSame(0, User::query()->count(), '被挡住时不能留下半个号');
            }
        }
    }

    // ---------- 工具 ----------

    private function makeMaleViewer(): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'account' => '13800000001',
            'phone' => '13800000001',
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_MALE,
            'nickname' => '阿宁',
            'avatar' => 'https://cdn.example.com/male.jpg',
            'city' => '上海',
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id, 'balance' => 100]);
        PointWallet::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function clear(): void
    {
        BatchAccount::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
