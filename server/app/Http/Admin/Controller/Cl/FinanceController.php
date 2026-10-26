<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClAdjustRequest;
use App\Http\Admin\Request\Cl\ClGrantRequest;
use App\Http\Admin\Request\Cl\ClRechargeRequest;
use App\Http\Admin\Request\Cl\ClWithdrawActionRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminFinanceService;
use App\Service\Cl\Admin\AdminWithdrawService;
use App\Service\Cl\Admin\ExcelExporter;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * 后台「资金管理」（docs/07 第 3.10~3.14 节）。
 *
 * 权限码**分得很细**（docs/07 第 4 节那张表）：客服能充值，财务能审核提现和看流水，
 * 但**客服调提现审核、财务调充值都要 403** —— 这两个 403 是 `07` 第 4 节点名要求的测试。
 * 「扣金币 / 调积分」那两个只有超管有（`cl:finance:coin-deduct` / `point-adjust`）。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class FinanceController extends AbstractController
{
    public function __construct(
        private readonly AdminFinanceService $financeService,
        private readonly AdminWithdrawService $withdrawService,
        private readonly ExcelExporter $excelExporter,
        private readonly AppConfigService $configService,
        private readonly CurrentUser $currentUser,
        private readonly HttpResponse $response,
    ) {}

    // ---------- 后台充值（P3.10 / P3.11）----------

    #[Post(
        path: '/admin/cl/finance/recharge',
        operationId: 'clFinanceRecharge',
        summary: '后台充值',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:recharge')]
    #[ResultResponse(new Result())]
    public function recharge(ClRechargeRequest $request): Result
    {
        $data = $request->validated();

        return $this->success($this->financeService->recharge(
            $this->operator(),
            (int) $data['user_id'],
            (int) $data['coins'],
            (int) ($data['amount_fen'] ?? 0),
            (string) $data['pay_channel'],
            (string) ($data['remark'] ?? ''),
        ));
    }

    /**
     * 用户列表里的「加款」快捷操作：男号加金币、女号加积分，复用现有充值/积分补发逻辑。
     */
    #[Post(
        path: '/admin/cl/finance/grant',
        operationId: 'clFinanceGrant',
        summary: '加款（男号金币 / 女号积分）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:grant')]
    #[ResultResponse(new Result())]
    public function grant(ClGrantRequest $request): Result
    {
        $data = $request->validated();

        return $this->success($this->financeService->grant(
            $this->operator(),
            (int) $data['user_id'],
            (int) $data['amount'],
            (int) ($data['amount_fen'] ?? 0),
            (string) $data['remark'],
        ));
    }

    /**
     * 汇率（docs/06 第 2.1 节那两个 key）。
     *
     * 充值的「参考金额」和平台收入的换算都要用它，但 App 那个 `/app/config` 里没有汇率
     * （那是给 App 用的），所以给后台单独开一个只读的。
     */
    #[Get(
        path: '/admin/cl/finance/rate',
        operationId: 'clFinanceRate',
        summary: '汇率',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:recharge')]
    #[ResultResponse(new Result())]
    public function rate(): Result
    {
        return $this->success([
            'coin_per_yuan' => (int) $this->configService->get('coin.per_yuan', 10),
            'point_per_yuan' => (int) $this->configService->get('point.per_yuan', 100),
        ]);
    }

    #[Get(
        path: '/admin/cl/finance/recharge/list',
        operationId: 'clFinanceRechargeList',
        summary: '充值记录',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:recharge-list')]
    #[ResultResponse(new Result())]
    public function rechargeList(): Result
    {
        return $this->success($this->financeService->rechargeList($this->filters(), $this->getCurrentPage(), $this->getPageSize()));
    }

    /**
     * 在线支付单查单补发。回调丢了（用户付了钱但没到账）时由客服点这个。
     */
    #[Post(
        path: '/admin/cl/finance/recharge/{id}/sync',
        operationId: 'clFinanceRechargeSync',
        summary: '在线充值单查单补发',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:recharge')]
    #[ResultResponse(new Result())]
    public function syncRecharge(int $id): Result
    {
        return $this->success($this->financeService->syncRecharge($id));
    }

    // ---------- 流水（P3.12）----------

    #[Get(
        path: '/admin/cl/finance/coin-log',
        operationId: 'clFinanceCoinLog',
        summary: '金币流水',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:coin-log')]
    #[ResultResponse(new Result())]
    public function coinLog(): Result
    {
        return $this->success($this->financeService->coinLogs($this->filters(), $this->getCurrentPage(), $this->getPageSize()));
    }

    #[Get(
        path: '/admin/cl/finance/point-log',
        operationId: 'clFinancePointLog',
        summary: '积分流水',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:point-log')]
    #[ResultResponse(new Result())]
    public function pointLog(): Result
    {
        return $this->success($this->financeService->pointLogs($this->filters(), $this->getCurrentPage(), $this->getPageSize()));
    }

    // ---------- 超管的兜底手段（docs/06 第 8 节）----------

    #[Post(
        path: '/admin/cl/finance/coin-deduct',
        operationId: 'clFinanceCoinDeduct',
        summary: '扣减金币',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:coin-deduct')]
    #[ResultResponse(new Result())]
    public function coinDeduct(ClAdjustRequest $request): Result
    {
        $data = $request->validated();
        $this->financeService->deductCoins($this->operator(), (int) $data['user_id'], abs((int) $data['amount']), (string) $data['reason']);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/finance/point-adjust',
        operationId: 'clFinancePointAdjust',
        summary: '调整积分',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:point-adjust')]
    #[ResultResponse(new Result())]
    public function pointAdjust(ClAdjustRequest $request): Result
    {
        $data = $request->validated();
        $this->financeService->adjustPoints($this->operator(), (int) $data['user_id'], (int) $data['amount'], (string) $data['reason']);

        return $this->success();
    }

    // ---------- 平台收入（P3.14）----------

    #[Get(
        path: '/admin/cl/finance/income',
        operationId: 'clFinanceIncome',
        summary: '平台收入',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:income')]
    #[ResultResponse(new Result())]
    public function income(): Result
    {
        [$from, $to] = $this->dateRange();

        return $this->success($this->financeService->incomes(
            $from,
            $to,
            (int) $this->configService->get('coin.per_yuan', 10),
            (int) $this->configService->get('point.per_yuan', 100),
        ));
    }

    #[Get(
        path: '/admin/cl/finance/income/export',
        operationId: 'clFinanceIncomeExport',
        summary: '导出平台收入',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:finance:income')]
    #[ResultResponse(new Result())]
    public function incomeExport(): PsrResponse
    {
        [$from, $to] = $this->dateRange();
        $data = $this->financeService->incomes(
            $from,
            $to,
            (int) $this->configService->get('coin.per_yuan', 10),
            (int) $this->configService->get('point.per_yuan', 100),
        );

        $rows = [];
        foreach ($data['list'] as $row) {
            $rows[] = [
                (string) $row['date'],
                (string) (int) $row['message_coins'],
                (string) (int) $row['gift_coins'],
                (string) (int) $row['vip_coins'],
                (string) (int) $row['points'],
                sprintf('%d.%02d', intdiv((int) $row['income_fen'], 100), (int) $row['income_fen'] % 100),
            ];
        }

        $this->excelExporter->purgeOld();
        $path = $this->excelExporter->write(
            '平台收入',
            ['日期', '私信收入(金币)', '礼物收入(金币)', '会员收入(金币)', '发给女用户积分', '平台毛收入(元)'],
            $rows,
        );

        return $this->response->download($path, sprintf('平台收入-%s-%s.xlsx', $from, $to));
    }

    // ---------- 提现审核（P3.13）----------

    #[Get(
        path: '/admin/cl/withdraw/list',
        operationId: 'clWithdrawList',
        summary: '提现审核列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:withdraw:list')]
    #[ResultResponse(new Result())]
    public function withdrawList(): Result
    {
        [$from, $to] = $this->datetimeRange();

        return $this->success($this->withdrawService->list(
            $this->statusFilter(),
            $from,
            $to,
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Post(
        path: '/admin/cl/withdraw/{id}/approve',
        operationId: 'clWithdrawApprove',
        summary: '通过提现',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:withdraw:approve')]
    #[ResultResponse(new Result())]
    public function withdrawApprove(int $id): Result
    {
        $this->withdrawService->approve($id, $this->operator()->id);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/withdraw/{id}/reject',
        operationId: 'clWithdrawReject',
        summary: '驳回提现',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:withdraw:reject')]
    #[ResultResponse(new Result())]
    public function withdrawReject(int $id, ClWithdrawActionRequest $request): Result
    {
        $reason = trim((string) ($request->validated()['reason'] ?? ''));
        if ($reason === '') {
            return $this->error('驳回必须填写原因');
        }

        $this->withdrawService->reject($id, $this->operator()->id, $reason);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/withdraw/{id}/paid',
        operationId: 'clWithdrawPaid',
        summary: '标记已打款',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:withdraw:paid')]
    #[ResultResponse(new Result())]
    public function withdrawPaid(int $id, ClWithdrawActionRequest $request): Result
    {
        $proof = trim((string) ($request->validated()['pay_proof'] ?? ''));
        if ($proof === '') {
            return $this->error('标记已打款必须填打款流水号或备注');
        }

        $this->withdrawService->markPaid($id, $this->operator()->id, $proof);

        return $this->success();
    }

    #[Get(
        path: '/admin/cl/withdraw/export',
        operationId: 'clWithdrawExport',
        summary: '导出提现单（给财务打款用）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 资金管理']
    )]
    #[Permission(code: 'cl:withdraw:list')]
    #[ResultResponse(new Result())]
    public function withdrawExport(): PsrResponse
    {
        [$from, $to] = $this->datetimeRange();
        $status = $this->statusFilter();
        $table = $this->withdrawService->exportRows($status, $from, $to);

        $this->excelExporter->purgeOld();
        $path = $this->excelExporter->write('提现单', $table['headers'], $table['rows']);

        return $this->response->download($path, sprintf('提现单-%s.xlsx', date('Ymd-His')));
    }

    // ---------- 内部 ----------

    private function filters(): array
    {
        $all = $this->getRequestData();
        $filters = [];
        foreach (['user_id', 'operator_id', 'pay_channel', 'type', 'created_from', 'created_to'] as $key) {
            if (isset($all[$key]) && $all[$key] !== '') {
                $filters[$key] = $all[$key];
            }
        }

        return $filters;
    }

    /** 提现的状态筛选：不传就当「待审核」。 */
    private function statusFilter(): ?int
    {
        $status = $this->getRequest()->input('status');

        return $status === null || $status === '' ? null : (int) $status;
    }

    /** 收入报表的日期范围：默认最近 30 天（`date` 列，所以给日期就行）。 */
    private function dateRange(): array
    {
        return [
            $this->input('from', date('Y-m-d', strtotime('-29 days'))),
            $this->input('to', date('Y-m-d')),
        ];
    }

    /**
     * 提现单的日期范围：`created_at` 是 datetime，**「截止到某天」要把那一天的 23:59:59 算进去**，
     * 不然当天的单子全被挡在外面（真踩到了：列表空的，数据其实在）。
     */
    private function datetimeRange(): array
    {
        [$from, $to] = $this->dateRange();

        return [
            strlen($from) === 10 ? $from . ' 00:00:00' : $from,
            strlen($to) === 10 ? $to . ' 23:59:59' : $to,
        ];
    }

    private function input(string $key, string $default): string
    {
        $value = trim((string) ($this->getRequest()->input($key) ?? ''));

        return $value === '' ? $default : $value;
    }

    private function operator(): AdminUser
    {
        $user = $this->currentUser->user();
        if ($user === null) {
            throw new \RuntimeException('后台操作人取不到');
        }

        return $user;
    }
}
