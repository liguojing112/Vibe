<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\IncomeLogsRequest;
use App\Model\Cl\User;
use App\Service\Cl\Income\IncomeQueryService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 女用户的收益与等级（docs/05 第 4.8 节，P26/P27）。
 *
 * 三个接口都是**只读**的：改钱在钱包服务里，提现在 `/withdraw/*`。
 */
#[OA\HyperfServer(name: 'http')]
final class IncomeController
{
    public function __construct(
        private readonly IncomeQueryService $incomeQueryService,
    ) {}

    #[OA\Get(
        path: '/api/v1/income/summary',
        operationId: 'clIncomeSummary',
        summary: '我的收益：可用/冻结积分、约合人民币、今日三项',
        tags: ['app:income']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function summary(): ApiResult
    {
        return ApiResult::success($this->incomeQueryService->summary($this->currentUser()));
    }

    #[OA\Get(
        path: '/api/v1/income/logs',
        operationId: 'clIncomeLogs',
        summary: '积分流水（分页）',
        tags: ['app:income']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function logs(IncomeLogsRequest $request): ApiResult
    {
        return ApiResult::success($this->incomeQueryService->logs(
            $this->currentUser(),
            (int) $request->input('page', 1),
        ));
    }

    #[OA\Get(
        path: '/api/v1/income/level',
        operationId: 'clIncomeLevel',
        summary: '我的等级与收费（只读）：单价、分成、全部等级表',
        tags: ['app:income']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function level(): ApiResult
    {
        return ApiResult::success($this->incomeQueryService->level($this->currentUser()));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
