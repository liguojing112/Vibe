<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\Report;
use App\Model\Cl\User;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminReportService;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 后台举报处理（docs/07 第 3.9 节）。
 *
 * 最要紧的一条：**处理完一定要通知举报人** —— 举报了没下文，下次就没人举报了。
 * 另外「判定违规 + 填封禁原因」要真的把人封上（走的是和用户管理同一个封禁入口）。
 *
 * @internal
 * @coversNothing
 */
final class AdminReportServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminReportService $service;

    private AdminUser $operator;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminReportService::class);
        // 上一次跑失败中断时 tearDown 不会执行，先清干净
        AdminUser::query()->where('username', 'kefu')->delete();
        $this->operator = AdminUser::create(['username' => 'kefu', 'password' => '123456', 'nickname' => '客服']);
    }

    protected function tearDown(): void
    {
        $this->clear();
        AdminUser::query()->where('username', 'kefu')->delete();
        parent::tearDown();
    }

    public function testListCarriesBothSidesAndReasonLabel(): void
    {
        [$reporter, $target] = $this->makeReport(Report::REASON_FRAUD, '他一直在发广告');

        $result = $this->service->list(Report::STATUS_PENDING, 1, 20);

        self::assertSame(1, $result['total']);
        $row = $result['list'][0];
        self::assertSame('阿宁', $row['reporter_nickname']);
        self::assertSame('晚晴', $row['target_nickname']);
        self::assertSame('广告诈骗', $row['reason_label']);
        self::assertSame('待处理', $row['status_text']);
        self::assertSame($reporter->id, (int) $row['reporter_id']);
        self::assertSame($target->id, (int) $row['target_id']);
    }

    public function testHandleViolationNotifiesReporter(): void
    {
        [$reporter, $target] = $this->makeReport(Report::REASON_FRAUD, '发广告');

        $this->service->handle($this->operator, (int) Report::query()->value('id'), true, '确认违规');

        $report = Report::query()->firstOrFail();
        self::assertSame(Report::STATUS_VIOLATION, (int) $report->status);
        self::assertSame('确认违规', $report->handle_result);
        self::assertSame($this->operator->id, (int) $report->handler_id);
        self::assertNotNull($report->handled_at);

        $notice = Notice::query()->where('user_id', $reporter->id)->firstOrFail();
        self::assertSame('举报已处理', $notice->title);
        self::assertStringContainsString('晚晴', $notice->content, '要告诉举报人处理的是谁');
        self::assertSame('REPORT', $notice->biz_type);

        self::assertSame(User::STATUS_NORMAL, $target->refresh()->status, '只判定不封号时人不该被动');
    }

    public function testHandleInnocentAlsoNotifiesReporter(): void
    {
        [$reporter] = $this->makeReport(Report::REASON_ABUSE, '他骂我');

        $this->service->handle($this->operator, (int) Report::query()->value('id'), false, '未发现违规');

        self::assertSame(Report::STATUS_INNOCENT, (int) Report::query()->value('status'));
        $notice = Notice::query()->where('user_id', $reporter->id)->firstOrFail();
        self::assertSame('举报已核实', $notice->title);
    }

    /** 判定违规 + 填了封禁原因 → 顺手封号（和用户管理同一个入口，通知/流水都全）。 */
    public function testHandleViolationWithBanReasonBansTarget(): void
    {
        [, $target] = $this->makeReport(Report::REASON_PORN, '发色情内容');

        $this->service->handle($this->operator, (int) Report::query()->value('id'), true, '确认违规', 7, '发布色情内容');

        $target->refresh();
        self::assertSame(User::STATUS_BANNED, $target->status);
        self::assertSame('发布色情内容', $target->ban_reason);
        self::assertNotNull($target->ban_until);

        self::assertSame(1, AdminActionLog::query()->where('user_id', $target->id)->where('action', AdminActionLog::ACTION_BAN)->count());
        self::assertSame(1, Notice::query()->where('user_id', $target->id)->where('biz_type', 'BAN')->count());
    }

    /** 判定「不违规」时就算填了封禁原因也不封（界面上那个框会藏起来，服务端也要挡）。 */
    public function testInnocentNeverBans(): void
    {
        [, $target] = $this->makeReport(Report::REASON_OTHER, '随便举报');

        $this->service->handle($this->operator, (int) Report::query()->value('id'), false, '查无实据', 7, '顺手封一下');

        self::assertSame(User::STATUS_NORMAL, $target->refresh()->status);
    }

    public function testHandlingTwiceIsRejected(): void
    {
        $this->makeReport(Report::REASON_OTHER, '举报');
        $id = (int) Report::query()->value('id');
        $this->service->handle($this->operator, $id, true, '');

        $this->expectException(ClBusinessException::class);
        $this->service->handle($this->operator, $id, false, '');
    }

    public function testListFiltersByStatus(): void
    {
        $this->makeReport(Report::REASON_OTHER, 'a');
        $this->makeReport(Report::REASON_OTHER, 'b');
        $this->service->handle($this->operator, (int) Report::query()->value('id'), true, '');

        self::assertSame(1, $this->service->list(Report::STATUS_PENDING, 1, 20)['total']);
        self::assertSame(1, $this->service->list(Report::STATUS_VIOLATION, 1, 20)['total']);
        self::assertSame(0, $this->service->list(Report::STATUS_INNOCENT, 1, 20)['total']);
    }

    // ---------- 工具 ----------

    /** @return array{0: User, 1: User} */
    private function makeReport(string $reason, string $content): array
    {
        $reporter = $this->makeUser(User::GENDER_MALE, '阿宁');
        $target = $this->makeUser(User::GENDER_FEMALE, '晚晴');

        Report::query()->create([
            'reporter_id' => $reporter->id,
            'target_id' => $target->id,
            'reason' => $reason,
            'content' => $content,
            'images' => ['https://cdn.example.com/shot.jpg'],
            'status' => Report::STATUS_PENDING,
        ]);

        return [$reporter, $target];
    }

    private function makeUser(int $gender, string $nickname): User
    {
        $phone = '13400000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $nickname,
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
        Report::query()->delete();
        AdminActionLog::query()->delete();
        Notice::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
