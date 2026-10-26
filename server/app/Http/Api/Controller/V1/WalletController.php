<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\CoinLogsRequest;
use App\Http\Api\Request\V1\CreateRechargeOrderRequest;
use App\Model\Cl\User;
use App\Service\Cl\Pay\RechargePayService;
use App\Service\Cl\Wallet\WalletQueryService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;

/**
 * 金币钱包（docs/05 第 4.7 节，P13/P22/P24）。**只给男用户**，除支付回调外都要登录。
 *
 * 在线支付是客户 2026-10-26 新增的需求：App 自己生成二维码 + 唤醒支付宝，
 * 不跳转码支付网站；后台人工充值仍然保留。
 */
#[OA\HyperfServer(name: 'http')]
final class WalletController
{
    public function __construct(
        private readonly WalletQueryService $walletQueryService,
        private readonly RechargePayService $rechargePayService,
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

    #[OA\Post(
        path: '/api/v1/wallet/recharge/order',
        operationId: 'clWalletRechargeCreateOrder',
        summary: '创建在线充值单并取支付链接（P22）',
        tags: ['app:wallet']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function createRechargeOrder(CreateRechargeOrderRequest $request): ApiResult
    {
        $data = $request->validated();

        return ApiResult::success($this->rechargePayService->create(
            $this->currentUser(),
            (int) $data['pack_index'],
            $this->clientIp($request),
        ));
    }

    #[OA\Get(
        path: '/api/v1/wallet/recharge/order/{orderNo}',
        operationId: 'clWalletRechargeOrderStatus',
        summary: '查询在线充值单状态（P22 轮询）',
        tags: ['app:wallet']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function rechargeOrder(string $orderNo): ApiResult
    {
        return ApiResult::success($this->rechargePayService->status($this->currentUser(), $orderNo));
    }

    /**
     * 码支付异步通知。**不加 AppAuthMiddleware**：这是支付平台调过来的，没有 App token。
     * 安全性靠 MD5 验签 + pid + 金额核对（`RechargePayService::handleNotify`）。
     */
    #[OA\Get(
        path: '/api/v1/wallet/recharge/notify',
        operationId: 'clWalletRechargeNotifyGet',
        summary: '码支付异步通知',
        tags: ['app:wallet']
    )]
    public function rechargeNotifyGet(RequestInterface $request): string
    {
        return $this->handleNotify($request);
    }

    #[OA\Post(
        path: '/api/v1/wallet/recharge/notify',
        operationId: 'clWalletRechargeNotifyPost',
        summary: '码支付异步通知（POST）',
        tags: ['app:wallet']
    )]
    public function rechargeNotifyPost(RequestInterface $request): string
    {
        return $this->handleNotify($request);
    }

    private function handleNotify(RequestInterface $request): string
    {
        $params = array_merge(
            $request->getQueryParams(),
            (array) ($request->getParsedBody() ?? []),
        );

        return $this->rechargePayService->handleNotify($params) ? 'success' : 'fail';
    }

    /** 码支付要求 clientip；生产在 Nginx 后面，优先后取转发头。 */
    private function clientIp(RequestInterface $request): string
    {
        $header = $request->getHeaderLine('x-forwarded-for');
        $comma = strpos($header, ',');
        $forwarded = trim($comma === false ? $header : substr($header, 0, $comma));

        if ($forwarded !== '') {
            return $forwarded;
        }

        return (string) ($request->getServerParams()['remote_addr'] ?? '');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
