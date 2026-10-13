<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\RealNameSubmitRequest;
use App\Model\Cl\User;
use App\Service\Cl\RealName\RealNameService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 实名认证（docs/05 第 4.8 节，P29）。
 *
 * App 只能提交和看状态：**身份证号返回的永远是打码的**，审核在后台（T7）。
 */
#[OA\HyperfServer(name: 'http')]
final class RealNameController
{
    public function __construct(
        private readonly RealNameService $realNameService,
    ) {}

    #[OA\Get(
        path: '/api/v1/realname',
        operationId: 'clRealName',
        summary: '实名状态（身份证号打码）',
        tags: ['app:realname']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function show(): ApiResult
    {
        return ApiResult::success($this->realNameService->info($this->currentUser()));
    }

    #[OA\Post(
        path: '/api/v1/realname/submit',
        operationId: 'clRealNameSubmit',
        summary: '提交实名（姓名 + 18 位身份证号）',
        tags: ['app:realname']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function submit(RealNameSubmitRequest $request): ApiResult
    {
        return ApiResult::success($this->realNameService->submit(
            $this->currentUser(),
            (string) $request->input('real_name'),
            (string) $request->input('id_card'),
        ));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
