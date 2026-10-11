<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Exception\ClUnauthorizedException;
use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\ChangePasswordRequest;
use App\Http\Api\Request\V1\LoginRequest;
use App\Http\Api\Request\V1\RefreshRequest;
use App\Http\Api\Request\V1\RegisterRequest;
use App\Model\Cl\User;
use App\Service\Cl\Auth\AppAuthService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Stringable\Str;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Swagger\Annotation\Post;
use Lcobucci\JWT\UnencryptedToken;
use Throwable;

/**
 * App 认证接口（docs/05 第 4.1 节）。
 * 全部返回 ApiResult（docs/05 第 1 节）；前缀 /api/v1。
 */
#[OA\HyperfServer(name: 'http')]
final class AuthController
{
    public function __construct(
        private readonly AppAuthService $authService,
    ) {}

    #[Post(
        path: '/api/v1/auth/register',
        operationId: 'clAuthRegister',
        summary: '注册',
        tags: ['app:auth']
    )]
    public function register(RegisterRequest $request): ApiResult
    {
        return ApiResult::success($this->authService->register(
            (string) $request->input('phone'),
            (string) $request->input('password'),
            (int) $request->input('gender'),
            (string) $request->input('nickname'),
        ));
    }

    #[Post(
        path: '/api/v1/auth/login',
        operationId: 'clAuthLogin',
        summary: '登录（账号或手机号）',
        tags: ['app:auth']
    )]
    public function login(LoginRequest $request): ApiResult
    {
        return ApiResult::success($this->authService->login(
            (string) $request->input('account'),
            (string) $request->input('password'),
            (string) ($request->getClientIps()[0] ?? '0.0.0.0'),
            (string) $request->input('device_id', ''),
        ));
    }

    #[Post(
        path: '/api/v1/auth/refresh',
        operationId: 'clAuthRefresh',
        summary: '刷新 token',
        tags: ['app:auth']
    )]
    public function refresh(RefreshRequest $request): ApiResult
    {
        $refreshToken = trim((string) $request->input('refresh_token', ''));
        if ($refreshToken === '') {
            $refreshToken = trim(Str::replace('Bearer ', '', (string) $request->header('Authorization', '')));
        }
        if ($refreshToken === '') {
            throw new ClUnauthorizedException();
        }

        try {
            $token = $this->authService->jwt()->parserRefreshToken($refreshToken);
        } catch (Throwable) {
            throw new ClUnauthorizedException('登录已失效，请重新登录');
        }

        return ApiResult::success($this->authService->refresh($token));
    }

    #[Post(
        path: '/api/v1/auth/logout',
        operationId: 'clAuthLogout',
        summary: '退出登录',
        tags: ['app:auth']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function logout(): ApiResult
    {
        /** @var UnencryptedToken $token */
        $token = Context::get('cl_token');
        $this->authService->logout($token);

        return ApiResult::success();
    }

    #[Post(
        path: '/api/v1/auth/password',
        operationId: 'clAuthPassword',
        summary: '修改密码',
        tags: ['app:auth']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function password(ChangePasswordRequest $request): ApiResult
    {
        /** @var User $user */
        $user = Context::get('cl_user');
        $this->authService->changePassword(
            $user,
            (string) $request->input('old_password'),
            (string) $request->input('new_password'),
        );

        return ApiResult::success();
    }
}
