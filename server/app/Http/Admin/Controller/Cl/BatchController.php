<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClBatchCreateRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminBatchService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「批量开号」（docs/07 第 3.3 节）。
 *
 * 和用户管理是一套菜单下的两件事，权限码也分开（`cl:user:batch`）：
 * 开号是一次性动作，客服不该有。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class BatchController extends AbstractController
{
    public function __construct(
        private readonly AdminBatchService $batchService,
        private readonly CurrentUser $currentUser,
    ) {}

    /**
     * 开一批号。
     *
     * **明文密码只在这个响应里出现一次**（docs/04 第 7 节）：服务端不存，
     * 前端拿到后当场下载，关掉弹窗就只能对单个账号重置密码。
     */
    #[Post(
        path: '/admin/cl/user/batch-create',
        operationId: 'clUserBatchCreate',
        summary: '批量开号',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:batch')]
    #[ResultResponse(new Result())]
    public function create(ClBatchCreateRequest $request): Result
    {
        $data = $request->validated();

        return $this->success($this->batchService->create(
            $this->operator(),
            (int) $data['gender'],
            (int) $data['count'],
            (string) ($data['prefix'] ?? ''),
            (string) ($data['remark'] ?? ''),
            (int) ($data['initial_amount'] ?? 0),
            (bool) ($data['assign_avatar'] ?? true),
        ));
    }

    #[Get(
        path: '/admin/cl/user/batch/list',
        operationId: 'clUserBatchList',
        summary: '开号批次列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:batch')]
    #[ResultResponse(new Result())]
    public function list(): Result
    {
        return $this->success(
            $this->batchService->batches($this->getCurrentPage(), $this->getPageSize())
        );
    }

    /**
     * 标记这批的明文已经导出过了。
     *
     * docs/05 第 6 节把这条写成 `GET .../export`（「只能导出一次」），
     * 但 docs/04 第 7 节明说**服务端不保存明文**，所以服务端根本拿不出第二次密码 ——
     * 文件是前端拿内存里的明文生成的。这里改成 POST 做标记（GET 不该有副作用），
     * 对应 `cl_batch_account.exported`，也就是批次列表那一列「是否已导出」。
     */
    #[Post(
        path: '/admin/cl/user/batch/{batchNo}/export',
        operationId: 'clUserBatchExported',
        summary: '标记批次明文已导出',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:batch')]
    #[ResultResponse(new Result())]
    public function exported(string $batchNo): Result
    {
        $this->batchService->markExported($batchNo);

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
