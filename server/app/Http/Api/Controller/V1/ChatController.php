<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\ChatReadRequest;
use App\Http\Api\Request\V1\ChatSendRequest;
use App\Model\Cl\User;
use App\Service\Cl\Ai\AiChatService;
use App\Service\Cl\Chat\ChatQueryService;
use App\Service\Cl\Chat\MessageBillingService;
use App\Service\Cl\Pricing\MessagePricingService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;

/**
 * 私信接口（docs/05 第 4.5 节）。全部要登录。
 *
 * 发消息（T4.2）与查询/已读（T4.3）都在这里：**路由前缀一样，只是动作不同**，
 * 分成两个 Controller 反而要重复写鉴权和取当前用户。
 */
#[OA\HyperfServer(name: 'http')]
final class ChatController
{
    public function __construct(
        private readonly MessageBillingService $billingService,
        private readonly ChatQueryService $queryService,
        private readonly MessagePricingService $pricingService,
        private readonly AiChatService $aiChatService,
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
        $result = $this->billingService->send(
            $this->currentUser(),
            (int) $request->input('receiver_id'),
            (int) $request->input('type'),
            (string) $request->input('content'),
            (string) $request->input('client_msg_id'),
        );

        // 对方女号开了 AI 代聊的话，异步生成回复（不阻塞这次发送）
        $this->aiChatService->handleIncomingById((int) ($result['message']['id'] ?? 0));

        return ApiResult::success($result);
    }

    #[OA\Get(
        path: '/api/v1/chat/conversations',
        operationId: 'clChatConversations',
        summary: '会话列表（App 端本地持久化，本接口用于首次拉取 + 静默刷新）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function conversations(RequestInterface $request): ApiResult
    {
        $page = max(1, (int) $request->input('page', 1));
        $result = $this->queryService->conversations($this->currentUser(), $page);

        return ApiResult::page($result['list'], $result['total'], $page, ChatQueryService::PAGE_SIZE);
    }

    #[OA\Delete(
        path: '/api/v1/chat/conversations/{id}',
        operationId: 'clChatConversationDelete',
        summary: '删除会话（只对自己隐藏，对方再来消息会重新出现）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function deleteConversation(RequestInterface $request): ApiResult
    {
        $this->queryService->deleteConversation($this->currentUser(), (int) $request->route('id'));

        return ApiResult::success();
    }

    #[OA\Get(
        path: '/api/v1/chat/conversations/with/{userId}',
        operationId: 'clChatConversationWith',
        summary: '进聊天页：会话 ID（没有则 null）+ 收费信息',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function with(RequestInterface $request): ApiResult
    {
        return ApiResult::success($this->queryService->with($this->currentUser(), (int) $request->route('userId')));
    }

    #[OA\Get(
        path: '/api/v1/chat/messages',
        operationId: 'clChatMessages',
        summary: '历史消息（倒序，上拉传 before_id；App 端以本地库为主，本接口用于补拉）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function messages(RequestInterface $request): ApiResult
    {
        $beforeId = (int) $request->input('before_id', 0);
        $result = $this->queryService->messages(
            $this->currentUser(),
            (int) $request->input('conversation_id', 0),
            $beforeId > 0 ? $beforeId : null,
            $this->limit($request, ChatQueryService::MESSAGE_PAGE_SIZE, ChatQueryService::MESSAGE_MAX_LIMIT),
        );

        return ApiResult::cursor($result['list'], $result['has_more']);
    }

    #[OA\Post(
        path: '/api/v1/chat/read',
        operationId: 'clChatRead',
        summary: '标记会话已读（返回最新未读总览，客户端不用再查一次）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function read(ChatReadRequest $request): ApiResult
    {
        return ApiResult::success(
            $this->queryService->markRead($this->currentUser(), (int) $request->input('conversation_id')),
        );
    }

    #[OA\Get(
        path: '/api/v1/chat/unread',
        operationId: 'clChatUnread',
        summary: '未读总览：会话 / 访客 / 系统通知分别返回',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function unread(): ApiResult
    {
        return ApiResult::success($this->queryService->unread($this->currentUser()));
    }

    #[OA\Get(
        path: '/api/v1/chat/sync',
        operationId: 'clChatSync',
        summary: '增量补拉我收到的、id 大于 after_id 的消息（重连 / 回前台）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function sync(RequestInterface $request): ApiResult
    {
        return ApiResult::success($this->queryService->sync(
            $this->currentUser(),
            max(0, (int) $request->input('after_id', 0)),
            $this->limit($request, ChatQueryService::SYNC_MAX_LIMIT, ChatQueryService::SYNC_MAX_LIMIT),
        ));
    }

    #[OA\Get(
        path: '/api/v1/chat/fee-rules',
        operationId: 'clChatFeeRules',
        summary: '收费说明弹窗的条目（按当前配置现场生成）',
        tags: ['app:chat']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function feeRules(): ApiResult
    {
        return ApiResult::success(['items' => $this->pricingService->feeRuleItems($this->currentUser())]);
    }

    private function limit(RequestInterface $request, int $default, int $max): int
    {
        $limit = (int) $request->input('limit', $default);

        return min(max($limit, 1), $max);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
