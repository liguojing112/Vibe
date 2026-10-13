<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\TaskClaimRequest;
use App\Model\Cl\User;
use App\Service\Cl\Task\TaskService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 每日任务（docs/05 第 4.9 节，P11）。
 *
 * 完成条件全部由服务端现算，App 只负责点按钮（`docs/01` P11）。
 */
#[OA\HyperfServer(name: 'http')]
final class TaskController
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    #[OA\Get(
        path: '/api/v1/task/list',
        operationId: 'clTaskList',
        summary: '适用我性别的任务 + 状态 + 进度',
        tags: ['app:task']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function list(): ApiResult
    {
        return ApiResult::success($this->taskService->list($this->currentUser()));
    }

    #[OA\Post(
        path: '/api/v1/task/sign-in',
        operationId: 'clTaskSignIn',
        summary: '签到（顺带把签到任务的奖励发了）',
        tags: ['app:task']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function signIn(): ApiResult
    {
        return ApiResult::success($this->taskService->signIn($this->currentUser()));
    }

    #[OA\Post(
        path: '/api/v1/task/claim',
        operationId: 'clTaskClaim',
        summary: '领取任务奖励（服务端会重新判断完成条件）',
        tags: ['app:task']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function claim(TaskClaimRequest $request): ApiResult
    {
        return ApiResult::success($this->taskService->claim(
            $this->currentUser(),
            (int) $request->input('task_id'),
        ));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
