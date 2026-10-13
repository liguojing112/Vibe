<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\WithdrawApplyRequest;
use App\Http\Api\Request\V1\WithdrawRecordsRequest;
use App\Model\Cl\User;
use App\Service\Cl\Withdraw\WithdrawService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 提现（docs/05 第 4.8 节，P28）。只有女用户有。
 *
 * 审核是后台的事（`docs/08` T7 接 HTTP），App 只能申请和看记录。
 */
#[OA\HyperfServer(name: 'http')]
final class WithdrawController
{
    public function __construct(
        private readonly WithdrawService $withdrawService,
    ) {}

    #[OA\Get(
        path: '/api/v1/withdraw/info',
        operationId: 'clWithdrawInfo',
        summary: '提现页：可提现金额、档位、规则、实名状态、收款信息、今日剩余次数',
        tags: ['app:withdraw']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function info(): ApiResult
    {
        return ApiResult::success($this->withdrawService->info($this->currentUser()));
    }

    #[OA\Post(
        path: '/api/v1/withdraw/apply',
        operationId: 'clWithdrawApply',
        summary: '申请提现（积分冻结，等财务审核）',
        tags: ['app:withdraw']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function apply(WithdrawApplyRequest $request): ApiResult
    {
        return ApiResult::success($this->withdrawService->apply(
            $this->currentUser(),
            (int) $request->input('amount_yuan'),
            (string) $request->input('account_type'),
            (string) $request->input('account_no'),
            $request->input('bank_name') === null ? null : (string) $request->input('bank_name'),
        ));
    }

    #[OA\Get(
        path: '/api/v1/withdraw/records',
        operationId: 'clWithdrawRecords',
        summary: '提现记录（分页）',
        tags: ['app:withdraw']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function records(WithdrawRecordsRequest $request): ApiResult
    {
        return ApiResult::success($this->withdrawService->records(
            $this->currentUser(),
            (int) $request->input('page', 1),
        ));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
