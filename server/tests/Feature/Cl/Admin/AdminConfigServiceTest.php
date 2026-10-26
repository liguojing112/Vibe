<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\City;
use App\Model\Cl\Gift;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Tag;
use App\Model\Cl\Task;
use App\Model\Cl\VipPlan;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminCatalogService;
use App\Service\Cl\Admin\AdminConfigService;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 后台运营配置（docs/07 第 3.15~3.23 节）。
 *
 * 两件最要紧的事：
 * - **存完 App 立刻能看到**（配置有 Redis 缓存，写完必须清）；
 * - **不能存坏值**（汇率 0 会让 App 那边换算炸掉、分成 200% 会让平台倒贴）。
 *
 * @internal
 * @coversNothing
 */
final class AdminConfigServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminConfigService $service;

    private AdminCatalogService $catalogService;

    private AdminUser $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminConfigService::class);
        $this->catalogService = make(AdminCatalogService::class);
        AdminUser::query()->where('username', 'admin')->delete();
        $this->operator = AdminUser::create(['username' => 'admin', 'password' => '123456', 'nickname' => '超管']);

        // 配置是**全表共享**的，改了会影响到别的测试类（计费那批就靠 free_count_per_pair）
        $this->originalFreeCount = (string) make(AppConfigService::class)->get('chat.free_count_per_pair', 3);
        $this->originalTiers = (string) json_encode(make(AppConfigService::class)->get('withdraw.tiers', []));
        $this->originalPacks = (string) json_encode(make(AppConfigService::class)->get('recharge.packs', []));
        $this->originalAiKey = (string) make(AppConfigService::class)->get('ai.api_key', '');
        $this->originalLv1Price = (int) LevelConfig::query()->where('level', 1)->value('message_price');
    }

    private string $originalFreeCount = '3';

    private string $originalTiers = '[50,100,200,500]';

    private string $originalPacks = '[]';

    private string $originalAiKey = '';

    private int $originalLv1Price = 5;

    protected function tearDown(): void
    {
        // 用完必须还原（T6.3 记过这条教训：测试改共享数据不还原，别的用例会莫名其妙挂）
        $this->service->save($this->operator, 'billing', ['chat.free_count_per_pair' => $this->originalFreeCount]);
        $this->service->save($this->operator, 'withdraw', ['withdraw.tiers' => $this->originalTiers]);
        $this->service->save($this->operator, 'recharge', ['recharge.packs' => $this->originalPacks]);
        make(AppConfigService::class)->set('ai.api_key', $this->originalAiKey);
        // 等级表也是共享的：改过单价要还原，不然别的测试（比如计费那批）会按错的价算
        LevelConfig::query()->where('level', 1)->update(['message_price' => $this->originalLv1Price]);
        $this->clear();
        AdminUser::query()->where('username', 'admin')->delete();
        parent::tearDown();
    }

    // ---------- 配置分组 ----------

    /** **`07` 第 3.15 节的验收**：改完配置 App 那边立刻看得到（缓存要清）。 */
    public function testSavingConfigFlushesCacheSoAppSeesItImmediately(): void
    {
        $config = make(AppConfigService::class);
        $before = (int) $config->get('chat.free_count_per_pair');
        $after = $before === 0 ? 7 : 0;

        $this->service->save($this->operator, 'billing', ['chat.free_count_per_pair' => (string) $after]);

        self::assertSame(
            $after,
            (int) make(AppConfigService::class)->get('chat.free_count_per_pair'),
            '存完必须清缓存，不然 App 还是老值',
        );
    }

    public function testGroupReturnsItemsWithLabels(): void
    {
        $result = $this->service->group('billing');

        self::assertSame('billing', $result['group']);
        $keys = array_column($result['items'], 'key');
        self::assertContains('coin.per_yuan', $keys);
        self::assertContains('chat.free_count_per_pair', $keys);

        $first = $result['items'][0];
        self::assertArrayHasKey('label', $first);
        self::assertArrayHasKey('value_type', $first);
    }

    /**
     * 一个页面只能改自己那组的 key —— 传别的组的 key **直接报错**（不静默忽略）。
     *
     * 静默忽略会吞掉真问题：Hyperf 的 `FormRequest::validated()` 会把数组键里的 `.` 换成 `->`，
     * 那样每个 key 都对不上、全被跳过，界面还显示「保存成功」。
     */
    public function testSaveRejectsKeysFromOtherGroups(): void
    {
        $before = (int) make(AppConfigService::class)->get('share.gift_rate', 3400);

        try {
            $this->service->save($this->operator, 'billing', ['share.gift_rate' => '9999']);
            self::fail('别的组的 key 应该被拒');
        } catch (ClBusinessException) {
            self::assertSame($before, (int) make(AppConfigService::class)->get('share.gift_rate'), '被拒时一个都不能改');
        }
    }

    /** 同一组里的正常 key 当然能存。 */
    public function testSaveWritesOwnGroupKeys(): void
    {
        $this->service->save($this->operator, 'billing', ['chat.free_count_per_pair' => '5']);

        self::assertSame(5, (int) make(AppConfigService::class)->get('chat.free_count_per_pair'));
    }

    /** 提现档位用可视化编辑器，但服务端仍然只认正整数数组。 */
    public function testSaveWithdrawTiers(): void
    {
        $this->service->save($this->operator, 'withdraw', ['withdraw.tiers' => '[30,60,120]']);

        self::assertSame([30, 60, 120], make(AppConfigService::class)->get('withdraw.tiers'));
    }

    /** 充值档位用可视化编辑器，服务端只认正整数金币/价格。 */
    public function testSaveRechargePacks(): void
    {
        $this->service->save($this->operator, 'recharge', [
            'recharge.packs' => '[{"coins":60,"yuan":6},{"coins":300,"yuan":30}]',
        ]);

        self::assertSame(
            [['coins' => 60, 'yuan' => 6], ['coins' => 300, 'yuan' => 30]],
            make(AppConfigService::class)->get('recharge.packs'),
        );
    }

    /** AI Key 不回显；留空保存不能把已有 Key 覆盖掉。 */
    public function testAiApiKeyIsHiddenAndBlankKeepsOldValue(): void
    {
        $this->service->save($this->operator, 'ai', ['ai.api_key' => 'sk-test-123']);

        $items = $this->service->group('ai')['items'];
        $aiKey = array_values(array_filter(
            $items,
            static fn (array $item): bool => $item['key'] === 'ai.api_key',
        ))[0];
        self::assertSame('', $aiKey['value'], '密钥不回显');

        $this->service->save($this->operator, 'ai', ['ai.api_key' => '']);
        self::assertSame('sk-test-123', make(AppConfigService::class)->get('ai.api_key'));
    }

    public function testRejectsBadValues(): void
    {
        $cases = [
            ['billing', ['coin.per_yuan' => '0'], '汇率不能是 0'],
            ['billing', ['coin.per_yuan' => 'abc'], '汇率必须是数字'],
            ['billing', ['chat.free_count_per_pair' => '-1'], '免费条数不能是负的'],
            ['share', ['share.gift_rate' => '10001'], '分成不能超过 10000'],
            ['share', ['share.unverified_factor' => '-1'], '分成系数不能是负的'],
            ['compliance', ['compliance.disclosure' => '   '], '合规声明不能存空'],
            ['feature', ['feature.call_enabled' => 'true'], '开关只能是 0/1'],
            ['withdraw', ['withdraw.tiers' => '[50,100,]'], 'JSON 不合法'],
            ['withdraw', ['withdraw.tiers' => '[-50]'], '档位不能有负数'],
            ['withdraw', ['withdraw.tiers' => '[50,"abc"]'], '档位必须是正整数'],
            ['withdraw', ['withdraw.tiers' => '[50,0]'], '档位必须是正整数'],
            ['recharge', ['recharge.packs' => '[{"coins":60}]'], '档位必须包含金币和价格'],
            ['recharge', ['recharge.packs' => '[{"coins":0,"yuan":6}]'], '金币必须是正整数'],
        ];

        foreach ($cases as [$group, $values, $why]) {
            try {
                $this->service->save($this->operator, $group, $values);
                self::fail("{$why}：应该被拒绝");
            } catch (ClBusinessException $e) {
                self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code, $why);
            }
        }
    }

    public function testRejectsUnknownGroup(): void
    {
        $this->expectException(ClBusinessException::class);
        $this->service->group('不存在');
    }

    // ---------- 等级配置 ----------

    public function testLevelsAreReturnedOrdered(): void
    {
        $levels = $this->service->levels();

        self::assertCount(5, $levels);
        self::assertSame(1, $levels[0]['level']);
        self::assertSame(0, $levels[0]['threshold_points'], 'Lv1 门槛是 0');
        self::assertSame(3, $levels[2]['level']);
    }

    public function testSavingLevelsTakesEffect(): void
    {
        $rows = $this->service->levels();
        $rows[0]['message_price'] = 8;

        $this->service->saveLevels($this->operator, $rows);

        self::assertSame(8, (int) LevelConfig::query()->where('level', 1)->value('message_price'));
    }

    public function testLevelsRejectBadTables(): void
    {
        $rows = $this->service->levels();

        // 门槛不递增
        $bad = $rows;
        $bad[1]['threshold_points'] = 0;
        $before = (int) LevelConfig::query()->where('level', 1)->value('message_price');
        $bad[0]['message_price'] = $before + 100;   // 顺便试改一行，校验不过就不该落库
        try {
            $this->service->saveLevels($this->operator, $bad);
            self::fail('门槛必须递增');
        } catch (ClBusinessException) {
            self::assertSame(
                $before,
                (int) LevelConfig::query()->where('level', 1)->value('message_price'),
                '校验不过时一行都不能改',
            );
        }

        // Lv1 门槛必须是 0
        $bad = $rows;
        $bad[0]['threshold_points'] = 100;
        $this->expectException(ClBusinessException::class);
        $this->service->saveLevels($this->operator, $bad);
    }

    public function testLevelsRejectOutOfRangeShare(): void
    {
        $rows = $this->service->levels();
        $rows[0]['message_share_rate'] = 10001;

        $this->expectException(ClBusinessException::class);
        $this->service->saveLevels($this->operator, $rows);
    }

    // ---------- 目录表 ----------

    public function testGiftCreateUpdateDelete(): void
    {
        $this->catalogService->saveGift($this->operator, null, ['name' => '小星星', 'icon' => '⭐', 'price_coins' => 20, 'sort' => 9, 'enabled' => 1]);
        $gift = Gift::query()->where('name', '小星星')->firstOrFail();
        self::assertSame(20, (int) $gift->price_coins);

        $this->catalogService->saveGift($this->operator, (int) $gift->id, ['name' => '小星星', 'icon' => '⭐', 'price_coins' => 30, 'sort' => 9, 'enabled' => 0]);
        self::assertSame(30, (int) $gift->refresh()->price_coins);
        self::assertSame(0, (int) $gift->enabled, '可以停用');

        self::assertSame(1, $this->catalogService->removeGifts([(int) $gift->id]));
        self::assertNull(Gift::query()->whereKey($gift->id)->first());
    }

    public function testGiftRejectsEmptyNameAndNegativePrice(): void
    {
        foreach ([
            ['name' => '', 'icon' => '⭐', 'price_coins' => 10],
            ['name' => '小星星', 'icon' => '', 'price_coins' => 10],
            ['name' => '小星星', 'icon' => '⭐', 'price_coins' => -1],
        ] as $data) {
            try {
                $this->catalogService->saveGift($this->operator, null, $data + ['sort' => 1, 'enabled' => 1]);
                self::fail('应该被拒绝：' . json_encode($data, JSON_UNESCAPED_UNICODE));
            } catch (ClBusinessException) {
                self::assertSame(0, Gift::query()->where('name', '小星星')->count());
            }
        }
    }

    public function testVipPlanRequiresPositiveDays(): void
    {
        $this->catalogService->saveVipPlan($this->operator, null, ['name' => '月卡', 'days' => 30, 'price_coins' => 390, 'sort' => 1, 'enabled' => 1]);
        self::assertSame(30, (int) VipPlan::query()->where('name', '月卡')->value('days'));

        $this->expectException(ClBusinessException::class);
        $this->catalogService->saveVipPlan($this->operator, null, ['name' => '零天卡', 'days' => 0, 'price_coins' => 1, 'sort' => 1, 'enabled' => 1]);
    }

    /** 任务类型只能是 `06` 第 7 节那四种。 */
    public function testTaskRejectsUnknownType(): void
    {
        $this->catalogService->saveTask($this->operator, null, [
            'name' => '每日签到', 'type' => Task::TYPE_SIGN_IN, 'target' => 1, 'reward' => 20,
            'gender' => 2, 'period' => 1, 'sort' => 1, 'enabled' => 1,
        ]);
        self::assertSame(1, Task::query()->where('name', '每日签到')->where('gender', 2)->count());

        $this->expectException(ClBusinessException::class);
        $this->catalogService->saveTask($this->operator, null, [
            'name' => '随便一个任务', 'type' => 'WHATEVER', 'target' => 1, 'reward' => 20,
            'gender' => 2, 'period' => 1, 'sort' => 1, 'enabled' => 1,
        ]);
    }

    public function testTagAndCityCrud(): void
    {
        $this->catalogService->saveTag($this->operator, null, ['name' => '露营', 'sort' => 11, 'enabled' => 1]);
        $this->catalogService->saveCity($this->operator, null, ['name' => '苏州', 'sort' => 11, 'enabled' => 1]);

        self::assertSame(1, Tag::query()->where('name', '露营')->count());
        self::assertSame(1, City::query()->where('name', '苏州')->count());

        $this->catalogService->saveTag($this->operator, (int) Tag::query()->where('name', '露营')->value('id'), ['name' => '露营', 'sort' => 11, 'enabled' => 0]);
        self::assertSame(0, (int) Tag::query()->where('name', '露营')->value('enabled'));
    }

    // ---------- 工具 ----------

    private function clear(): void
    {
        make(Redis::class)->del('cl:sensitive_words');
        make(AppConfigService::class)->forget();
        Gift::query()->where('name', '小星星')->delete();
        VipPlan::query()->where('name', '零天卡')->delete();
        Task::query()->where('name', '随便一个任务')->delete();
        Task::query()->where('name', '每日签到')->where('gender', 2)->where('target', 1)->delete();
        Tag::query()->where('name', '露营')->delete();
        City::query()->where('name', '苏州')->delete();
    }
}
