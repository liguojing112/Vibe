<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClCatalogSaveRequest;
use App\Http\Admin\Request\Cl\ClIdsRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\VipOrderRepository;
use App\Service\Cl\Admin\AdminCatalogService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「运营配置」里那几张目录表（docs/07 第 3.17、3.18、3.22 节）：
 * 礼物、会员套餐、每日任务、兴趣标签、城市，外加会员开通记录（只读）。
 *
 * 五个实体的增删改走**同一个服务**，所以这里的每个方法都只有两三行 ——
 * 真正要校验的东西（任务类型、天数、门槛）在 `AdminCatalogService` 里。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class CatalogController extends AbstractController
{
    public function __construct(
        private readonly AdminCatalogService $catalogService,
        private readonly VipOrderRepository $vipOrderRepository,
        private readonly CurrentUser $currentUser,
    ) {}

    // ---------- 礼物管理 ----------

    #[Get(path: '/admin/cl/gift/list', operationId: 'clGiftList', summary: '礼物列表', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:gift')]
    #[ResultResponse(new Result())]
    public function giftList(): Result
    {
        return $this->success($this->catalogService->gifts());
    }

    #[Post(path: '/admin/cl/gift/save', operationId: 'clGiftSave', summary: '新增/修改礼物', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:gift')]
    #[ResultResponse(new Result())]
    public function giftSave(ClCatalogSaveRequest $request): Result
    {
        $data = $request->validated();
        $this->catalogService->saveGift($this->operator(), $data['id'] ?? null, $data);

        return $this->success();
    }

    #[Post(path: '/admin/cl/gift/delete', operationId: 'clGiftDelete', summary: '删除礼物', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:gift')]
    #[ResultResponse(new Result())]
    public function giftDelete(ClIdsRequest $request): Result
    {
        return $this->success(['deleted' => $this->catalogService->removeGifts($request->validated()['ids'])]);
    }

    // ---------- 会员套餐 ----------

    #[Get(path: '/admin/cl/vip-plan/list', operationId: 'clVipPlanList', summary: '会员套餐列表', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:vip')]
    #[ResultResponse(new Result())]
    public function vipPlanList(): Result
    {
        return $this->success($this->catalogService->vipPlans());
    }

    #[Post(path: '/admin/cl/vip-plan/save', operationId: 'clVipPlanSave', summary: '新增/修改会员套餐', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:vip')]
    #[ResultResponse(new Result())]
    public function vipPlanSave(ClCatalogSaveRequest $request): Result
    {
        $data = $request->validated();
        $this->catalogService->saveVipPlan($this->operator(), $data['id'] ?? null, $data);

        return $this->success();
    }

    #[Post(path: '/admin/cl/vip-plan/delete', operationId: 'clVipPlanDelete', summary: '删除会员套餐', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:vip')]
    #[ResultResponse(new Result())]
    public function vipPlanDelete(ClIdsRequest $request): Result
    {
        return $this->success(['deleted' => $this->catalogService->removeVipPlans($request->validated()['ids'])]);
    }

    /** 会员开通记录（docs/07 第 3.18 节）：只读。 */
    #[Get(path: '/admin/cl/vip-order/list', operationId: 'clVipOrderList', summary: '会员开通记录', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:vip-order')]
    #[ResultResponse(new Result())]
    public function vipOrderList(): Result
    {
        $all = $this->getRequestData();
        $filters = [];
        foreach (['user_id', 'source'] as $key) {
            if (isset($all[$key]) && $all[$key] !== '') {
                $filters[$key] = $all[$key];
            }
        }

        return $this->success($this->vipOrderRepository->pageForAdmin($filters, $this->getCurrentPage(), $this->getPageSize()));
    }

    // ---------- 每日任务 ----------

    #[Get(path: '/admin/cl/task/list', operationId: 'clTaskList', summary: '任务列表', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:task')]
    #[ResultResponse(new Result())]
    public function taskList(): Result
    {
        return $this->success($this->catalogService->tasks());
    }

    #[Post(path: '/admin/cl/task/save', operationId: 'clTaskSave', summary: '新增/修改任务', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:task')]
    #[ResultResponse(new Result())]
    public function taskSave(ClCatalogSaveRequest $request): Result
    {
        $data = $request->validated();
        $this->catalogService->saveTask($this->operator(), $data['id'] ?? null, $data);

        return $this->success();
    }

    #[Post(path: '/admin/cl/task/delete', operationId: 'clTaskDelete', summary: '删除任务', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:task')]
    #[ResultResponse(new Result())]
    public function taskDelete(ClIdsRequest $request): Result
    {
        return $this->success(['deleted' => $this->catalogService->removeTasks($request->validated()['ids'])]);
    }

    // ---------- 兴趣标签 ----------

    #[Get(path: '/admin/cl/tag/list', operationId: 'clTagList', summary: '兴趣标签列表', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:tag-city')]
    #[ResultResponse(new Result())]
    public function tagList(): Result
    {
        return $this->success($this->catalogService->tags());
    }

    #[Post(path: '/admin/cl/tag/save', operationId: 'clTagSave', summary: '新增/修改标签', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:tag-city')]
    #[ResultResponse(new Result())]
    public function tagSave(ClCatalogSaveRequest $request): Result
    {
        $data = $request->validated();
        $this->catalogService->saveTag($this->operator(), $data['id'] ?? null, $data);

        return $this->success();
    }

    #[Post(path: '/admin/cl/tag/delete', operationId: 'clTagDelete', summary: '删除标签', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:tag-city')]
    #[ResultResponse(new Result())]
    public function tagDelete(ClIdsRequest $request): Result
    {
        return $this->success(['deleted' => $this->catalogService->removeTags($request->validated()['ids'])]);
    }

    // ---------- 城市 ----------

    #[Get(path: '/admin/cl/city/list', operationId: 'clCityList', summary: '城市列表', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:tag-city')]
    #[ResultResponse(new Result())]
    public function cityList(): Result
    {
        return $this->success($this->catalogService->cities());
    }

    #[Post(path: '/admin/cl/city/save', operationId: 'clCitySave', summary: '新增/修改城市', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:tag-city')]
    #[ResultResponse(new Result())]
    public function citySave(ClCatalogSaveRequest $request): Result
    {
        $data = $request->validated();
        $this->catalogService->saveCity($this->operator(), $data['id'] ?? null, $data);

        return $this->success();
    }

    #[Post(path: '/admin/cl/city/delete', operationId: 'clCityDelete', summary: '删除城市', security: [['Bearer' => [], 'ApiKey' => []]], tags: ['畅撩交友 / 运营配置'])]
    #[Permission(code: 'cl:operation:tag-city')]
    #[ResultResponse(new Result())]
    public function cityDelete(ClIdsRequest $request): Result
    {
        return $this->success(['deleted' => $this->catalogService->removeCities($request->validated()['ids'])]);
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
