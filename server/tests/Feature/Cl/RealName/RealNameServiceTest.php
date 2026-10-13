<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\RealName;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\RealNameRepository;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\RealName\RealNameService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 实名认证（docs/04 第 1 节、`docs/01` P29）。
 *
 * 四条硬规则：
 * - 身份证号**格式 + 校验位**都要对（错了 40003）；
 * - **审核中不能重复提交**（40002）；
 * - **库里没有明文**（只有密文、打码、hash）；
 * - 通过之后分成比例立刻按实名的算（未实名是打五折的）。
 *
 * @internal
 * @coversNothing
 */
final class RealNameServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    /** 校验位算得对的号（`110101199003071233` 的第 18 位是 3）。 */
    private const VALID_ID = '110101199003071233';

    /** 校验位是 X 的那种，也要收。 */
    private const VALID_ID_X = '11010119900307002X';

    /** 只把最后一位改错：格式对、校验位错。 */
    private const BAD_CHECKSUM_ID = '110101199003071234';

    private RealNameService $service;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->seedLevels();

        $config = make(AppConfigService::class);
        $config->set('share.unverified_factor', 5000);
        $config->set('share.gift_rate', 3400);
        $config->set('share.unverified_apply_gift', 0);

        $this->service = make(RealNameService::class);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 提交 ----------

    public function testSubmitStoresCiphertextMaskAndHashButNoPlaintext(): void
    {
        $female = $this->makeFemale();

        $result = $this->service->submit($female, '张三', self::VALID_ID);

        self::assertSame(User::REAL_NAME_AUDITING, $result['status']);
        self::assertSame('审核中', $result['status_text']);
        self::assertSame('张三', $result['real_name']);
        self::assertSame('110***********1233', $result['id_card_mask']);
        self::assertFalse($result['can_submit'], '审核中不能再提交');
        self::assertNull($result['reject_reason']);

        // cl_user 那一列要同步（App 的「我」页面读的是它）
        self::assertSame(User::REAL_NAME_AUDITING, (int) $female->refresh()->real_name_status);

        $row = RealName::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame('110***********1233', $row->id_card_mask);
        self::assertSame(hash('sha256', self::VALID_ID), $row->id_card_hash);
        self::assertSame(RealName::STATUS_AUDITING, (int) $row->status);

        // **库里没有明文**：密文和明文不一样，而且原样搜不到
        self::assertNotSame(self::VALID_ID, $row->id_card_encrypted);
        self::assertStringNotContainsString(self::VALID_ID, $row->id_card_encrypted);
        self::assertSame(
            0,
            RealName::query()->where('id_card_encrypted', 'like', '%' . self::VALID_ID . '%')->count(),
            '整个表里都搜不到明文',
        );

        // 后台要能还原（解密是给审核看的）
        self::assertSame(self::VALID_ID, $this->service->decrypt($row->id_card_encrypted));
    }

    public function testAcceptIdCardEndingWithX(): void
    {
        $female = $this->makeFemale();

        $result = $this->service->submit($female, '李四', self::VALID_ID_X);

        self::assertSame('110***********002X', $result['id_card_mask']);
        self::assertSame(hash('sha256', self::VALID_ID_X), RealName::query()->where('user_id', $female->id)->value('id_card_hash'));
    }

    public function testBadChecksumIsRejected(): void
    {
        $female = $this->makeFemale();

        $this->assertBizCode(BizCode::REAL_NAME_FORMAT_ERROR, function () use ($female): void {
            $this->service->submit($female, '张三', self::BAD_CHECKSUM_ID);
        });

        self::assertSame(0, RealName::query()->where('user_id', $female->id)->count(), '没写进去');
        self::assertSame(User::REAL_NAME_NONE, (int) $female->refresh()->real_name_status);
    }

    public function testWrongLengthOrIllegalCharIsRejected(): void
    {
        $female = $this->makeFemale();

        foreach (['12345678901234', '1101011990030712330', '1101011990030712ab', ''] as $bad) {
            $this->assertBizCode(BizCode::REAL_NAME_FORMAT_ERROR, function () use ($female, $bad): void {
                $this->service->submit($female, '张三', $bad);
            });
        }
    }

    public function testBlankRealNameIsRejected(): void
    {
        $female = $this->makeFemale();

        $this->assertBizCode(BizCode::REAL_NAME_FORMAT_ERROR, function () use ($female): void {
            $this->service->submit($female, '   ', self::VALID_ID);
        });
    }

    public function testAuditingCannotSubmitTwice(): void
    {
        $female = $this->makeFemale();
        $this->service->submit($female, '张三', self::VALID_ID);

        $this->assertBizCode(BizCode::PROFILE_AUDITING, function () use ($female): void {
            $this->service->submit($female, '张三', self::VALID_ID);
        });

        self::assertSame(1, RealName::query()->where('user_id', $female->id)->count());
    }

    public function testPassedCannotSubmitAgain(): void
    {
        $female = $this->makeFemale();
        $this->service->submit($female, '张三', self::VALID_ID);
        $this->service->approve($female, auditorId: 1);

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($female): void {
            $this->service->submit($female, '张三', self::VALID_ID);
        });
    }

    public function testRejectedCanSubmitAgainAndOverwrites(): void
    {
        $female = $this->makeFemale();
        $this->service->submit($female, '张三', self::VALID_ID);
        $this->service->reject($female, auditorId: 1, reason: '照片和本人不符');

        $after = $this->service->info($female->refresh());
        self::assertSame('已驳回', $after['status_text']);
        self::assertSame('照片和本人不符', $after['reject_reason']);
        self::assertTrue($after['can_submit'], '被驳回可以重新提交');

        $result = $this->service->submit($female, '张三', self::VALID_ID_X);

        self::assertSame('审核中', $result['status_text']);
        self::assertNull($result['reject_reason'], '重新提交要把上次的驳回原因清掉');
        self::assertSame(1, RealName::query()->where('user_id', $female->id)->count(), '是覆盖不是新增');
        self::assertSame('110***********002X', $result['id_card_mask']);
    }

    // ---------- 审核 ----------

    public function testApproveTurnsOnRealNameShareRateAndNotifies(): void
    {
        $female = $this->makeFemale();
        $this->service->submit($female, '张三', self::VALID_ID);

        // 未实名：Lv3 的 4000 只能拿 2000
        $pricing = make(MessagePricingService::class);
        self::assertSame(2000, $pricing->shareRateOf($female->refresh(), 4000));

        $this->service->approve($female, auditorId: 42);

        $female->refresh();
        self::assertSame(User::REAL_NAME_PASSED, (int) $female->real_name_status);
        self::assertSame(4000, $pricing->shareRateOf($female, 4000), '通过之后按实名的算，不打折');

        $row = RealName::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame(RealName::STATUS_PASSED, (int) $row->status);
        self::assertSame(42, (int) $row->auditor_id);
        self::assertNotNull($row->audited_at);

        $notice = Notice::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame('实名认证已通过', $notice->title);
        self::assertSame('REAL_NAME', $notice->biz_type);
    }

    public function testRejectKeepsReasonAndNotifies(): void
    {
        $female = $this->makeFemale();
        $this->service->submit($female, '张三', self::VALID_ID);

        $this->service->reject($female, auditorId: 42, reason: '身份证照片不清晰');

        $female->refresh();
        self::assertSame(User::REAL_NAME_REJECTED, (int) $female->real_name_status);
        $row = RealName::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame('身份证照片不清晰', $row->reject_reason);

        $notice = Notice::query()->where('user_id', $female->id)->firstOrFail();
        self::assertSame('实名认证被驳回', $notice->title);
        self::assertSame('身份证照片不清晰', $notice->content);
    }

    public function testRejectNeedsReason(): void
    {
        $female = $this->makeFemale();
        $this->service->submit($female, '张三', self::VALID_ID);

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($female): void {
            $this->service->reject($female, auditorId: 42, reason: '  ');
        });
    }

    public function testApproveWithoutSubmissionIsRejected(): void
    {
        $female = $this->makeFemale();

        $this->assertBizCode(BizCode::STATUS_NOT_ALLOWED, function () use ($female): void {
            $this->service->approve($female, auditorId: 1);
        });
    }

    // ---------- 查询 ----------

    public function testInfoForUserWhoNeverSubmitted(): void
    {
        $info = $this->service->info($this->makeFemale());

        self::assertSame(User::REAL_NAME_NONE, $info['status']);
        self::assertSame('未认证', $info['status_text']);
        self::assertSame('', $info['real_name']);
        self::assertSame('', $info['id_card_mask']);
        self::assertNull($info['reject_reason']);
        self::assertTrue($info['can_submit']);
    }

    public function testSameIdCardOnTwoAccountsIsAllowedButFindable(): void
    {
        $first = $this->makeFemale();
        $second = $this->makeFemale();

        $this->service->submit($first, '张三', self::VALID_ID);
        // 一家人共用一张身份证是合理的，**不拦截**
        $this->service->submit($second, '张三', self::VALID_ID);

        $hash = hash('sha256', self::VALID_ID);
        $found = make(RealNameRepository::class)->userIdsByHash($hash, exceptUserId: $second->id);

        self::assertSame([$first->id], $found, '后台能查到「这号别的账号也用过」');
    }

    // ---------- 工具 ----------

    /**
     * @param callable(): void $block
     */
    private function assertBizCode(BizCode $expected, callable $block): void
    {
        try {
            $block();
            self::fail('应该抛业务错误 ' . $expected->value);
        } catch (ClBusinessException $e) {
            self::assertSame($expected->value, $e->getResponse()->code);
        }
    }

    private function seedLevels(): void
    {
        $rows = [
            1 => [0, 5, 3000],
            2 => [20000, 8, 3500],
            3 => [100000, 10, 4000],
            4 => [300000, 15, 4500],
            5 => [1000000, 20, 5000],
        ];

        // 按 level 覆盖写、**不删整表**：`cl_level_config` 是别的测试类也在用的共享种子，
        // 删掉它会让后跑的用例拿不到等级配置（`GiftBillingTest` 也是这么干的）。
        foreach ($rows as $level => [$threshold, $price, $rate]) {
            $attributes = [
                'name' => 'Lv' . $level,
                'threshold_points' => $threshold,
                'message_price' => $price,
                'message_share_rate' => $rate,
                'voice_price' => 60 * $level,
                'video_price' => 120 * $level,
            ];
            $existing = LevelConfig::query()->where('level', $level)->first();
            if ($existing === null) {
                LevelConfig::query()->create(array_merge(['level' => $level], $attributes));
            } else {
                $existing->update($attributes);
            }
        }
    }

    private function makeFemale(int $level = 3): User
    {
        $index = ++$this->sequence;
        $female = User::query()->create([
            'account' => 'rnfemale' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => User::GENDER_FEMALE,
            'nickname' => '女' . $index,
            'level' => $level,
            'real_name_status' => User::REAL_NAME_NONE,
            'status' => User::STATUS_NORMAL,
        ]);

        make(PointWalletService::class)->createForUser($female->id);
        make(CoinWalletService::class)->createForUser($female->id);

        return $female->refresh();
    }

    private function clearAll(): void
    {
        Notice::query()->delete();
        RealName::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
