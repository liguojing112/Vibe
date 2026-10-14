<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClAuditBatchRequest;
use App\Http\Admin\Request\Cl\ClAuditRejectRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\RealName;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminAuditService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「审核中心」（docs/07 第 3.4、3.5 节）。
 *
 * 两个权限码分开：`cl:audit:profile`（资料）和 `cl:audit:realname`（实名）；
 * 另外**看身份证明文**是第三个码 `cl:audit:realname-plain`，连审核员都没有，只有超管有 ——
 * 那是能直接定位到真人的信息。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class AuditController extends AbstractController
{
    public function __construct(
        private readonly AdminAuditService $auditService,
        private readonly CurrentUser $currentUser,
    ) {}

    // ---------- 资料审核 ----------

    #[Get(
        path: '/admin/cl/audit/profile/list',
        operationId: 'clAuditProfileList',
        summary: '资料审核列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:profile')]
    #[ResultResponse(new Result())]
    public function profileList(): Result
    {
        return $this->success($this->auditService->profileList(
            $this->statusFilter(ProfileAudit::STATUS_PENDING),
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Post(
        path: '/admin/cl/audit/profile/{id}/approve',
        operationId: 'clAuditProfileApprove',
        summary: '资料审核通过',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:profile')]
    #[ResultResponse(new Result())]
    public function profileApprove(int $id): Result
    {
        $this->auditService->approveProfile($this->operator(), $id);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/audit/profile/{id}/reject',
        operationId: 'clAuditProfileReject',
        summary: '资料审核驳回',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:profile')]
    #[ResultResponse(new Result())]
    public function profileReject(int $id, ClAuditRejectRequest $request): Result
    {
        $this->auditService->rejectProfile($this->operator(), $id, (string) $request->validated()['reason']);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/audit/profile/batch',
        operationId: 'clAuditProfileBatch',
        summary: '批量通过/驳回资料',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:profile')]
    #[ResultResponse(new Result())]
    public function profileBatch(ClAuditBatchRequest $request): Result
    {
        $data = $request->validated();

        return $this->success($this->auditService->batchProfile(
            $this->operator(),
            array_map('intval', $data['ids']),
            $data['action'] === 'approve',
            (string) ($data['reason'] ?? ''),
        ));
    }

    // ---------- 实名审核 ----------

    #[Get(
        path: '/admin/cl/audit/realname/list',
        operationId: 'clAuditRealNameList',
        summary: '实名审核列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:realname')]
    #[ResultResponse(new Result())]
    public function realNameList(): Result
    {
        return $this->success($this->auditService->realNameList(
            $this->statusFilter(RealName::STATUS_AUDITING),
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Post(
        path: '/admin/cl/audit/realname/{id}/approve',
        operationId: 'clAuditRealNameApprove',
        summary: '实名审核通过',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:realname')]
    #[ResultResponse(new Result())]
    public function realNameApprove(int $id): Result
    {
        $this->auditService->approveRealName($this->operator(), $id);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/audit/realname/{id}/reject',
        operationId: 'clAuditRealNameReject',
        summary: '实名审核驳回',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:realname')]
    #[ResultResponse(new Result())]
    public function realNameReject(int $id, ClAuditRejectRequest $request): Result
    {
        $this->auditService->rejectRealName($this->operator(), $id, (string) $request->validated()['reason']);

        return $this->success();
    }

    /** 看身份证明文（每看一次写一条流水）。这个权限码连审核员都没有。 */
    #[Get(
        path: '/admin/cl/audit/realname/{id}/plain',
        operationId: 'clAuditRealNamePlain',
        summary: '查看身份证明文',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 审核中心']
    )]
    #[Permission(code: 'cl:audit:realname-plain')]
    #[ResultResponse(new Result())]
    public function realNamePlain(int $id): Result
    {
        return $this->success($this->auditService->plainIdCard($this->operator(), $id));
    }

    // ---------- 内部 ----------

    /** 列表的状态筛选：不传就用这个页面的「默认那个 Tab」。 */
    private function statusFilter(int $default): int
    {
        $status = $this->getRequest()->input('status');

        return $status === null || $status === '' ? $default : (int) $status;
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
