<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\CoinLogsRequest;
use App\Model\Cl\User;
use App\Service\Cl\Wallet\WalletQueryService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;

/**
 * 金币钱包（docs/05 第 4.7 节，P13/P22/P24）。**只给男用户**，全部要登录。
 *
 * 只读：真正的充值由后台客服做（docs/06 第 8 节），App 里点「充值」只是把客服联系方式给用户。
 */
#[OA\HyperfServer(name: 'http')]
final class WalletController
{
    public function __construct(
        private readonly WalletQueryService $walletQueryService,
    ) {}

    #[OA\Get(
        path: '/api/v1/wallet/coin',
        operationId: 'clWalletCoin',
        summary: '金币余额与单价范围（P13）',
        tags: ['app:wallet']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function coin(): ApiResult
    {
        return ApiResult::success($this->walletQueryService->coin($this->currentUser()));
    }

    #[OA\Get(
        path: '/api/v1/wallet/coin/logs',
        operationId: 'clWalletCoinLogs',
        summary: '金币收支明细（P24：all / recharge / spend）',
        tags: ['app:wallet']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function coinLogs(CoinLogsRequest $request): ApiResult
    {
        $page = max(1, (int) $request->input('page', 1));
        $result = $this->walletQueryService->coinLogs(
            $this->currentUser(),
            (string) $request->input('type', 'all'),
            $page,
        );

        return ApiResult::page($result['list'], $result['total'], $page, WalletQueryService::PAGE_SIZE);
    }

    #[OA\Get(
        path: '/api/v1/wallet/recharge-info',
        operationId: 'clWalletRechargeInfo',
        summary: '充值档位、客服信息、合规声明（P22）',
        tags: ['app:wallet']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function rechargeInfo(): ApiResult
    {
        return ApiResult::success($this->walletQueryService->rechargeInfo($this->currentUser()));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
