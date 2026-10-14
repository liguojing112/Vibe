<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClReportHandleRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Cl\Report;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminReportService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「举报处理」（docs/07 第 3.9 节）。
 *
 * 权限码和聊天管理分开：`cl:report:list` / `cl:report:handle` —— 客服有这两个，
 * 因为它就是处理用户投诉的角色。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly AdminReportService $reportService,
        private readonly CurrentUser $currentUser,
    ) {}

    #[Get(
        path: '/admin/cl/report/list',
        operationId: 'clReportList',
        summary: '举报列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 举报处理']
    )]
    #[Permission(code: 'cl:report:list')]
    #[ResultResponse(new Result())]
    public function list(): Result
    {
        $status = $this->getRequest()->input('status');

        return $this->success($this->reportService->list(
            $status === null || $status === '' ? Report::STATUS_PENDING : (int) $status,
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    /** 判定违规 / 不违规（违规时可以在同一个请求里顺手封了被举报人）。 */
    #[Post(
        path: '/admin/cl/report/{id}/handle',
        operationId: 'clReportHandle',
        summary: '处理举报',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 举报处理']
    )]
    #[Permission(code: 'cl:report:handle')]
    #[ResultResponse(new Result())]
    public function handle(int $id, ClReportHandleRequest $request): Result
    {
        $data = $request->validated();

        $this->reportService->handle(
            $this->operator(),
            $id,
            (bool) $data['violation'],
            (string) ($data['remark'] ?? ''),
            (int) ($data['ban_days'] ?? 0),
            (string) ($data['ban_reason'] ?? ''),
        );

        return $this->success();
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
