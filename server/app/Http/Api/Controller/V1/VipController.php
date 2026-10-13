<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\VipBuyRequest;
use App\Model\Cl\User;
use App\Service\Cl\Vip\VipService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 会员中心（docs/05 第 4.9 节，P12）。
 *
 * 只有男用户能进（女用户的「我」页面里没有会员入口，docs/01 P12 标注「男」）。
 */
#[OA\HyperfServer(name: 'http')]
final class VipController
{
    public function __construct(
        private readonly VipService $vipService,
    ) {}

    #[OA\Get(
        path: '/api/v1/vip/info',
        operationId: 'clVipInfo',
        summary: '会员中心：套餐、权益、我的到期时间',
        tags: ['app:vip']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function info(): ApiResult
    {
        return ApiResult::success($this->vipService->info($this->currentUser()));
    }

    #[OA\Post(
        path: '/api/v1/vip/buy',
        operationId: 'clVipBuy',
        summary: '用金币购买会员套餐（续费在原到期时间上顺延）',
        tags: ['app:vip']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function buy(VipBuyRequest $request): ApiResult
    {
        return ApiResult::success($this->vipService->buy(
            $this->currentUser(),
            (int) $request->input('plan_id'),
            (string) $request->input('client_order_id'),
        ));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
