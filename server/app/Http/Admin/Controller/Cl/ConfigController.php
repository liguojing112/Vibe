<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClConfigSaveRequest;
use App\Http\Admin\Request\Cl\ClLevelsSaveRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Exception\BusinessException;
use App\Http\Common\Result;
use App\Http\Common\ResultCode;
use App\Http\CurrentUser;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminConfigService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Put;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「运营配置」里的配置分组与等级表（docs/07 第 3.15、3.16、3.19、3.21、3.23 节）。
 *
 * 一个接口管八组配置：**权限码跟着分组走**（`GROUP_PERMISSIONS`）——
 * 客服只有「系统通知」那一组，超管才有全部。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class ConfigController extends AbstractController
{
    /** 分组 → 能改它的权限码（docs/07 第 4 节：运营配置整块是超管的）。 */
    private const GROUP_PERMISSIONS = [
        'billing' => 'cl:operation:billing',
        'share' => 'cl:operation:billing',
        'chat' => 'cl:operation:billing',
        'vip' => 'cl:operation:billing',
        'withdraw' => 'cl:operation:billing',
        'recharge' => 'cl:operation:recharge',
        'pay' => 'cl:operation:pay',
        'agreement' => 'cl:operation:agreement',
        'compliance' => 'cl:operation:agreement',
        'feature' => 'cl:operation:feature',
    ];

    public function __construct(
        private readonly AdminConfigService $configService,
        private readonly CurrentUser $currentUser,
    ) {}

    #[Get(
        path: '/admin/cl/config/groups',
        operationId: 'clConfigGroups',
        summary: '配置分组列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    #[Permission(code: 'cl:operation:billing')]
    #[ResultResponse(new Result())]
    public function groups(): Result
    {
        return $this->success([
            'all' => AdminConfigService::GROUPS,
            'billing' => AdminConfigService::BILLING_GROUPS,
        ]);
    }

    #[Get(
        path: '/admin/cl/config/levels',
        operationId: 'clConfigLevels',
        summary: '等级配置',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    #[Permission(code: 'cl:operation:level')]
    #[ResultResponse(new Result())]
    public function levels(): Result
    {
        return $this->success($this->configService->levels());
    }

    #[Put(
        path: '/admin/cl/config/levels',
        operationId: 'clConfigLevelsSave',
        summary: '保存等级配置',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    #[Permission(code: 'cl:operation:level')]
    #[ResultResponse(new Result())]
    public function saveLevels(ClLevelsSaveRequest $request): Result
    {
        $this->configService->saveLevels($this->operator(), $request->validated()['items']);

        return $this->success();
    }

    #[Get(
        path: '/admin/cl/config/{group}',
        operationId: 'clConfigGroup',
        summary: '读一组配置',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    public function group(string $group): Result
    {
        $this->assertGroupAllowed($group);

        return $this->success($this->configService->group($group));
    }

    #[Put(
        path: '/admin/cl/config/{group}',
        operationId: 'clConfigGroupSave',
        summary: '保存一组配置',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    public function saveGroup(string $group, ClConfigSaveRequest $request): Result
    {
        $this->assertGroupAllowed($group);

        // ⚠️ 这里**不能**用 `$request->validated()['values']`：
        // Hyperf 的 FormRequest 会把数组键里的 `.` 换成 `->`
        // （JSON 里的 `chat.free_count_per_pair` 到 `validated()` 里变成 `chat->free_count_per_pair`，
        // 而 `$request->all()` 里是对的）。运营配置的 key 全是带点的，用它这里就全废了。
        $values = (array) $this->getRequest()->input('values', []);
        $this->configService->save($this->operator(), $group, $values);

        return $this->success();
    }

    /**
     * 分组权限在**方法里**判，而不是用 `#[Permission]` —— 注解是编译期固定的，
     * 而这里要按 URL 里的 `{group}` 决定查哪个码。`PermissionMiddleware` 已经按
     * 「有其中一个权限就放行」把用户信息准备好了，这里再逐个核对一遍。
     */
    private function assertGroupAllowed(string $group): void
    {
        $code = self::GROUP_PERMISSIONS[$group] ?? null;
        $user = $this->operator();

        if ($code === null || (! $user->isSuperAdmin() && ! $user->hasPermission($code))) {
            // 和 `PermissionMiddleware` 一样抛 403（不是 40001）—— 这是权限问题，不是业务状态问题
            throw new BusinessException(code: ResultCode::FORBIDDEN);
        }
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
