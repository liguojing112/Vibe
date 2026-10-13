<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\GiftSendRequest;
use App\Model\Cl\Gift;
use App\Model\Cl\User;
use App\Repository\Cl\GiftRepository;
use App\Service\Cl\Gift\GiftBillingService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 礼物（docs/05 第 4.6 节）：列表 + 赠送。
 *
 * P23（整页面板）和 P31（聊天页底部弹窗）用**同一个礼物列表**，所以只有这一个 `/gift/list`。
 */
#[OA\HyperfServer(name: 'http')]
final class GiftController
{
    public function __construct(
        private readonly GiftRepository $giftRepository,
        private readonly GiftBillingService $giftBillingService,
    ) {}

    #[OA\Get(
        path: '/api/v1/gift/list',
        operationId: 'clGiftList',
        summary: '礼物列表（只返回上架的）',
        tags: ['app:gift']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function list(): ApiResult
    {
        $items = [];
        foreach ($this->giftRepository->enabledOrdered() as $gift) {
            $items[] = $this->payload($gift);
        }

        return ApiResult::success(['list' => $items]);
    }

    #[OA\Post(
        path: '/api/v1/gift/send',
        operationId: 'clGiftSend',
        summary: '送礼物（扣男方金币、女方按礼物分成拿积分）',
        tags: ['app:gift']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function send(GiftSendRequest $request): ApiResult
    {
        return ApiResult::success($this->giftBillingService->send(
            $this->currentUser(),
            (int) $request->input('receiver_id'),
            (int) $request->input('gift_id'),
            (string) $request->input('client_order_id'),
        ));
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(Gift $gift): array
    {
        return [
            'id' => (int) $gift->id,
            'name' => (string) $gift->name,
            'icon' => (string) $gift->icon,
            'price_coins' => (int) $gift->price_coins,
        ];
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
