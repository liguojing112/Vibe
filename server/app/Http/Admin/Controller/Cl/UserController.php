<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClUserBanRequest;
use App\Http\Admin\Request\Cl\ClUserGenderRequest;
use App\Http\Admin\Request\Cl\ClUserLevelRequest;
use App\Http\Admin\Request\Cl\ClUserUpdateRequest;
use App\Http\Admin\Request\Cl\ClUserVipGrantRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Cl\User;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminUserRepository;
use App\Service\Cl\Admin\AdminUserService;
use App\Service\Cl\Admin\ExcelExporter;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\Put;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;
use Psr\Http\Message\ResponseInterface as PsrResponse;

/**
 * 后台「用户管理」（docs/07 第 3.2 节）。
 *
 * 男用户和女用户是**同一个接口**，靠 `gender` 区分（前端也是同一个页面组件）。
 * 每个接口都挂了权限码，权限码就是 `cl_menu_seeder` 里那几个菜单名 ——
 * `PermissionMiddleware` 拿它去比 `menu.name`（docs/07 第 4 节）。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly AdminUserService $userService,
        private readonly ExcelExporter $excelExporter,
        private readonly CurrentUser $currentUser,
        private readonly HttpResponse $response,
    ) {}

    #[Get(
        path: '/admin/cl/user/list',
        operationId: 'clUserList',
        summary: '用户列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:list')]
    #[ResultResponse(new Result())]
    public function list(): Result
    {
        return $this->success(
            $this->userService->list(
                $this->filters(),
                $this->getCurrentPage(),
                $this->getPageSize(),
            )
        );
    }

    /**
     * 导出当前筛选结果（docs/07 第 3.2 节）。
     *
     * 返回的是文件流，不是 `Result` —— 前端按 blob 下载。
     */
    #[Get(
        path: '/admin/cl/user/export',
        operationId: 'clUserExport',
        summary: '导出用户列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:list')]
    #[ResultResponse(new Result())]
    public function export(): PsrResponse
    {
        $filters = $this->filters();
        $female = (int) ($filters['gender'] ?? User::GENDER_MALE) === User::GENDER_FEMALE;

        $this->excelExporter->purgeOld();
        $table = $this->userService->exportRows($filters, $female);
        $path = $this->excelExporter->write($female ? '女用户' : '男用户', $table['headers'], $table['rows']);

        return $this->response->download($path, sprintf('%s用户-%s.xlsx', $female ? '女' : '男', date('Ymd-His')));
    }

    #[Get(
        path: '/admin/cl/user/{id}',
        operationId: 'clUserDetail',
        summary: '用户详情（抽屉）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:detail')]
    #[ResultResponse(new Result())]
    public function detail(int $id): Result
    {
        return $this->success($this->userService->detail($id));
    }

    #[Put(
        path: '/admin/cl/user/{id}',
        operationId: 'clUserUpdate',
        summary: '直接修改用户资料',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:update')]
    #[ResultResponse(new Result())]
    public function update(int $id, ClUserUpdateRequest $request): Result
    {
        $this->userService->updateProfile($this->operator(), $id, $request->validated());

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/user/{id}/reset-password',
        operationId: 'clUserResetPassword',
        summary: '重置登录密码',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:reset-password')]
    #[ResultResponse(new Result())]
    public function resetPassword(int $id): Result
    {
        // 明文只在这一个响应里出现，前端必须弹窗显示一次（docs/07 第 3.2 节）
        return $this->success($this->userService->resetPassword($this->operator(), $id));
    }

    #[Post(
        path: '/admin/cl/user/{id}/ban',
        operationId: 'clUserBan',
        summary: '封禁用户',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:ban')]
    #[ResultResponse(new Result())]
    public function ban(int $id, ClUserBanRequest $request): Result
    {
        $data = $request->validated();
        $this->userService->ban($this->operator(), $id, (string) $data['reason'], (int) ($data['days'] ?? 0));

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/user/{id}/unban',
        operationId: 'clUserUnban',
        summary: '解封用户',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:ban')]
    #[ResultResponse(new Result())]
    public function unban(int $id, ClUserBanRequest $request): Result
    {
        $this->userService->unban($this->operator(), $id, (string) $request->validated()['reason']);

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/user/{id}/level',
        operationId: 'clUserLevel',
        summary: '调整女用户等级',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:level')]
    #[ResultResponse(new Result())]
    public function level(int $id, ClUserLevelRequest $request): Result
    {
        $data = $request->validated();
        $this->userService->setLevel(
            $this->operator(),
            $id,
            (int) $data['level'],
            (bool) ($data['locked'] ?? false),
            (string) $data['reason'],
        );

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/user/{id}/vip-grant',
        operationId: 'clUserVipGrant',
        summary: '开通/延长会员',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:vip-grant')]
    #[ResultResponse(new Result())]
    public function vipGrant(int $id, ClUserVipGrantRequest $request): Result
    {
        $data = $request->validated();
        $this->userService->grantVip(
            $this->operator(),
            $id,
            isset($data['plan_id']) ? (int) $data['plan_id'] : null,
            (int) ($data['days'] ?? 0),
            (string) ($data['remark'] ?? ''),
        );

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/user/{id}/gender',
        operationId: 'clUserGender',
        summary: '修改用户性别',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 用户管理']
    )]
    #[Permission(code: 'cl:user:gender')]
    #[ResultResponse(new Result())]
    public function gender(int $id, ClUserGenderRequest $request): Result
    {
        $data = $request->validated();
        $this->userService->changeGender($this->operator(), $id, (int) $data['gender'], (string) $data['reason']);

        return $this->success();
    }

    /** 只取列表/导出用得上的那几个查询参数，别把 `page` 之类也塞进 where。 */
    private function filters(): array
    {
        $all = $this->getRequestData();
        $filters = ['gender' => (int) ($all['gender'] ?? User::GENDER_MALE)];
        foreach (AdminUserRepository::FILTER_KEYS as $key) {
            if (isset($all[$key])) {
                $filters[$key] = $all[$key];
            }
        }

        return $filters;
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
