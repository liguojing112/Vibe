<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Service\Cl\Admin\AdminDashboardService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「数据看板」（docs/07 第 3.1 节）。
 *
 * 一个接口返回全部卡片 + 近 30 天的三个序列：看板一进来就要全部数据，
 * 拆成七八个请求的话页面上会看到卡片一块一块地跳出来。
 * 四个角色都有 `cl:dashboard` 权限（docs/07 第 4 节），所以只有这一道权限码。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly AdminDashboardService $dashboardService,
    ) {}

    #[Get(
        path: '/admin/cl/dashboard/overview',
        operationId: 'clDashboardOverview',
        summary: '数据看板',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 数据看板']
    )]
    #[Permission(code: 'cl:dashboard')]
    #[ResultResponse(new Result())]
    public function overview(): Result
    {
        return $this->success($this->dashboardService->overview());
    }
}
