<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\UpdatePhotosRequest;
use App\Http\Api\Request\V1\UpdateProfileRequest;
use App\Http\Api\Request\V1\UpdateSettingsRequest;
use App\Model\Cl\User;
use App\Service\Cl\User\ProfileService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * App 用户资料（docs/05 第 4.2 节）。四个接口都要登录。
 *
 * 注：这个文件原本是 MineAdmin 脚手架里的示例（一个不干活的 `/api/v1/login`），
 * 已换成项目真实的资料接口；App 登录在 `AuthController` 的 `/api/v1/auth/login`。
 */
#[OA\HyperfServer(name: 'http')]
final class UserController
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    #[OA\Get(
        path: '/api/v1/user/me',
        operationId: 'clUserMe',
        summary: '我的完整资料（含待审内容与审核状态）',
        tags: ['app:user']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function me(): ApiResult
    {
        return ApiResult::success($this->profileService->me($this->currentUser()));
    }

    #[OA\Post(
        path: '/api/v1/user/profile',
        operationId: 'clUserProfile',
        summary: '修改资料（头像 / 昵称 / 自我介绍进入审核）',
        tags: ['app:user']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function profile(UpdateProfileRequest $request): ApiResult
    {
        return ApiResult::success($this->profileService->update($this->currentUser(), $request->all()));
    }

    #[OA\Post(
        path: '/api/v1/user/photos',
        operationId: 'clUserPhotos',
        summary: '相册整体提交',
        tags: ['app:user']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function photos(UpdatePhotosRequest $request): ApiResult
    {
        return ApiResult::success([
            'photos' => $this->profileService->replacePhotos(
                $this->currentUser(),
                (array) $request->input('urls', []),
            ),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/user/settings',
        operationId: 'clUserSettings',
        summary: '展示开关（附近的人 / 推荐）',
        tags: ['app:user']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function settings(UpdateSettingsRequest $request): ApiResult
    {
        $this->profileService->updateSettings($this->currentUser(), $request->all());

        return ApiResult::success();
    }

    /**
     * 中间件已经保证用户存在且未封禁。
     */
    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
