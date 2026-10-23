<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClMomentStatusRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Service\Cl\Admin\AdminMomentService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「内容管理 / 朋友圈」（docs/07 之外的扩展）。
 *
 * 动态是**先发后审**的：运营在这里看列表、隐藏违规内容、必要时删除。
 * 隐藏只是把 `status` 改成 2，App 端立刻看不到、数据还在，可以恢复。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class MomentController extends AbstractController
{
    public function __construct(
        private readonly AdminMomentService $momentService,
    ) {}

    #[Get(
        path: '/admin/cl/moment/list',
        operationId: 'clMomentList',
        summary: '朋友圈动态列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:moment:list')]
    #[ResultResponse(new Result())]
    public function list(RequestInterface $request): Result
    {
        return $this->success($this->momentService->list(
            [
                'user_id' => $request->input('user_id'),
                'status' => $request->input('status'),
            ],
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Post(
        path: '/admin/cl/moment/{id}/status',
        operationId: 'clMomentSetStatus',
        summary: '隐藏 / 恢复动态',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:moment:hide')]
    #[ResultResponse(new Result())]
    public function setStatus(int $id, ClMomentStatusRequest $request): Result
    {
        $this->momentService->setStatus($id, (int) $request->validated()['status']);

        return $this->success();
    }

    #[Delete(
        path: '/admin/cl/moment/{id}',
        operationId: 'clMomentDelete',
        summary: '删除动态',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:moment:delete')]
    #[ResultResponse(new Result())]
    public function remove(int $id): Result
    {
        $this->momentService->remove($id);

        return $this->success();
    }

    #[Get(
        path: '/admin/cl/moment/{id}/comment',
        operationId: 'clMomentComments',
        summary: '某条动态的评论',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:moment:comment')]
    #[ResultResponse(new Result())]
    public function comments(int $id): Result
    {
        return $this->success($this->momentService->comments($id, $this->getCurrentPage(), $this->getPageSize()));
    }
}
