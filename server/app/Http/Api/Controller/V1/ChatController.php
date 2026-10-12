<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\ChatSendRequest;
use App\Model\Cl\User;
use App\Service\Cl\Chat\MessageBillingService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 私信接口（docs/05 第 4.5 节）。全部要登录。
 *
 * 这一段只做发消息（T4.2）；会话列表、历史消息、已读、未读、sync、收费说明在 T4.3。
 */
#[OA\HyperfServer(name: 'http')]
final class ChatController
{
    public function __construct(
        private readonly MessageBillingService $billingService,
    ) {}

    #[OA\Post(
        path: '/api/v1/chat/send',
        operationId: 'clChatSend',
        summary: '发私信（服务端计费：扣男方金币、女方按等级分成拿积分）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function send(ChatSendRequest $request): ApiResult
    {
        return ApiResult::success($this->billingService->send(
            $this->currentUser(),
            (int) $request->input('receiver_id'),
            (int) $request->input('type'),
            (string) $request->input('content'),
            (string) $request->input('client_msg_id'),
        ));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
