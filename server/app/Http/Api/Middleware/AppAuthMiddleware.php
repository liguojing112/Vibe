<?php

declare(strict_types=1);

namespace App\Http\Api\Middleware;

use App\Exception\ClUnauthorizedException;
use App\Model\Cl\User;
use App\Service\Cl\Auth\AppAuthService;
use Hyperf\Context\Context;
use Hyperf\Stringable\Str;
use Lcobucci\JWT\Token\RegisteredClaims;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * App 接口鉴权（docs/02 第 2.4 节、docs/05 第 2 节）。
 *
 * 校验 Authorization: Bearer <token>（scene = api）→ 黑名单 → 用户存在且未注销。
 * 未登录 / token 失效 → ClUnauthorizedException（HTTP 401 + code 401）；
 * 封禁中 → ClBusinessException(10004)。通过后把用户放进 Context 与 request attribute。
 */
final class AppAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AppAuthService $authService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $raw = $this->token($request);
        if ($raw === '') {
            throw new ClUnauthorizedException();
        }

        try {
            $jwt = $this->authService->jwt();
            $token = $jwt->parserAccessToken($raw);
            if ($jwt->hasBlackList($token)) {
                throw new ClUnauthorizedException('登录已失效，请重新登录');
            }
        } catch (ClUnauthorizedException $e) {
            throw $e;
        } catch (Throwable) {
            throw new ClUnauthorizedException('登录已失效，请重新登录');
        }

        $user = $this->authService->findActiveUser((int) $token->claims()->get(RegisteredClaims::ID));
        if (! $user instanceof User) {
            throw new ClUnauthorizedException('登录已失效，请重新登录');
        }
        // 封禁中会抛 10004（带 reason、until）
        $this->authService->assertNotBanned($user);

        Context::set('cl_user', $user);
        Context::set('cl_token', $token);

        return $handler->handle($request->withAttribute('cl_user', $user));
    }

    private function token(ServerRequestInterface $request): string
    {
        if (! $request->hasHeader('Authorization')) {
            return '';
        }
        return trim(Str::replace('Bearer ', '', $request->getHeaderLine('Authorization')));
    }
}
