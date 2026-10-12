<?php

declare(strict_types=1);

namespace App\Service\Cl\Push;

use App\Model\Cl\User;
use App\Service\Cl\Auth\AppAuthService;
use Lcobucci\JWT\Token\RegisteredClaims;
use Throwable;

/**
 * WebSocket 的连接鉴权（docs/05 第 5 节：`ws://{host}:9502/ws?token={access_token}`）。
 *
 * 和 HTTP 的 `AppAuthMiddleware` 走**同一套 JWT（scene = api）**，区别只有两点：
 * token 从 query 里拿（浏览器的 WebSocket API 不能自定义头，客户端保持一致），
 * 以及这里**不抛异常只返回 null** —— 握手阶段抛异常会变成 400，客户端只会看到「连不上」，
 * 我们要的是连上再明确踢掉（带 4001 关闭码），客户端好据此清登录态回登录页。
 */
final class WsAuthenticator
{
    public function __construct(
        private readonly AppAuthService $authService,
    ) {}

    /** token 有效返回用户，否则 null（无 token / 过期 / 进黑名单 / 已注销 / 封禁中都算无效）。 */
    public function resolve(string $rawToken): ?User
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return null;
        }

        try {
            $jwt = $this->authService->jwt();
            $token = $jwt->parserAccessToken($rawToken);
            if ($jwt->hasBlackList($token)) {
                return null;
            }

            $user = $this->authService->findActiveUser((int) $token->claims()->get(RegisteredClaims::ID));
            if (! $user instanceof User) {
                return null;
            }

            // 封禁中的账号连实时推送也不该收到
            $this->authService->assertNotBanned($user);

            return $user;
        } catch (Throwable) {
            return null;
        }
    }
}
