<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClChatReviewRequest;
use App\Http\Admin\Request\Cl\ClIdsRequest;
use App\Http\Admin\Request\Cl\ClSensitiveImportRequest;
use App\Http\Admin\Request\Cl\ClSensitiveWordRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminChatRepository;
use App\Service\Cl\Admin\AdminChatService;
use App\Service\Cl\Admin\AdminSensitiveWordService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Hyperf\Swagger\Annotation\Put;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「聊天管理」的前三个页面（docs/07 第 3.6~3.8 节）：会话记录、待审消息、敏感词库。
 *
 * 举报在 `ReportController` —— 它是独立第四页，权限码也不同（`cl:report:*`）。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly AdminChatService $chatService,
        private readonly AdminSensitiveWordService $sensitiveWordService,
        private readonly CurrentUser $currentUser,
    ) {}

    // ---------- 会话记录（P3.6）----------

    #[Get(
        path: '/admin/cl/chat/conversation/list',
        operationId: 'clChatConversationList',
        summary: '会话记录列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:conversation')]
    #[ResultResponse(new Result())]
    public function conversationList(): Result
    {
        return $this->success($this->chatService->conversations(
            $this->filters(),
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    /** 会话里的全部消息（带价格、免费原因、女方所得积分）。 */
    #[Get(
        path: '/admin/cl/chat/conversation/{id}/messages',
        operationId: 'clChatConversationMessages',
        summary: '会话消息详情',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:conversation')]
    #[ResultResponse(new Result())]
    public function conversationMessages(int $id): Result
    {
        return $this->success($this->chatService->conversationMessages(
            $id,
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    // ---------- 待审消息（P3.7）----------

    #[Get(
        path: '/admin/cl/chat/message/pending',
        operationId: 'clChatMessagePending',
        summary: '待审消息列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:pending')]
    #[ResultResponse(new Result())]
    public function pending(): Result
    {
        return $this->success($this->chatService->pendingMessages($this->getCurrentPage(), $this->getPageSize()));
    }

    /** 某条消息的前后几条（举报页「查看上下文」用）。 */
    #[Get(
        path: '/admin/cl/chat/message/{id}/context',
        operationId: 'clChatMessageContext',
        summary: '消息上下文',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:pending')]
    #[ResultResponse(new Result())]
    public function context(int $id): Result
    {
        return $this->success($this->chatService->messageContext($id, 5));
    }

    /** 标记正常 / 违规（会话详情和待审消息两个页面都调这个）。 */
    #[Post(
        path: '/admin/cl/chat/message/review',
        operationId: 'clChatMessageReview',
        summary: '标记消息正常/违规',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:pending')]
    #[ResultResponse(new Result())]
    public function review(ClChatReviewRequest $request): Result
    {
        $data = $request->validated();

        return $this->success($this->chatService->markMessages(
            $this->operator(),
            array_map('intval', $data['ids']),
            (int) $data['status'],
            (int) ($data['ban_days'] ?? 0),
            (string) ($data['ban_reason'] ?? ''),
        ));
    }

    // ---------- 敏感词库（P3.8）----------

    #[Get(
        path: '/admin/cl/chat/sensitive/list',
        operationId: 'clChatSensitiveList',
        summary: '敏感词列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:sensitive')]
    #[ResultResponse(new Result())]
    public function sensitiveList(): Result
    {
        return $this->success($this->sensitiveWordService->list(
            $this->getRequest()->input('keyword') === null ? null : (string) $this->getRequest()->input('keyword'),
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Post(
        path: '/admin/cl/chat/sensitive',
        operationId: 'clChatSensitiveCreate',
        summary: '新增敏感词',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:sensitive')]
    #[ResultResponse(new Result())]
    public function sensitiveCreate(ClSensitiveWordRequest $request): Result
    {
        $this->sensitiveWordService->create($this->operator(), (string) $request->validated()['word']);

        return $this->success();
    }

    #[Put(
        path: '/admin/cl/chat/sensitive/{id}',
        operationId: 'clChatSensitiveUpdate',
        summary: '修改敏感词',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:sensitive')]
    #[ResultResponse(new Result())]
    public function sensitiveUpdate(int $id, ClSensitiveWordRequest $request): Result
    {
        $data = $request->validated();
        $this->sensitiveWordService->update(
            $this->operator(),
            $id,
            (string) $data['word'],
            (bool) ($data['enabled'] ?? true),
        );

        return $this->success();
    }

    /** 批量删除（`DELETE` 带 body 不可靠，所以走 POST）。 */
    #[Post(
        path: '/admin/cl/chat/sensitive/delete',
        operationId: 'clChatSensitiveDelete',
        summary: '删除敏感词',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:sensitive')]
    #[ResultResponse(new Result())]
    public function sensitiveDelete(ClIdsRequest $request): Result
    {
        return $this->success([
            'deleted' => $this->sensitiveWordService->remove($this->operator(), array_map('intval', $request->validated()['ids'])),
        ]);
    }

    #[Post(
        path: '/admin/cl/chat/sensitive/import',
        operationId: 'clChatSensitiveImport',
        summary: '批量导入敏感词',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 聊天管理']
    )]
    #[Permission(code: 'cl:chat:sensitive')]
    #[ResultResponse(new Result())]
    public function sensitiveImport(ClSensitiveImportRequest $request): Result
    {
        return $this->success(
            $this->sensitiveWordService->import($this->operator(), (string) $request->validated()['text'])
        );
    }

    // ---------- 内部 ----------

    private function filters(): array
    {
        $all = $this->getRequestData();
        $filters = [];
        foreach (AdminChatRepository::FILTER_KEYS as $key) {
            if (isset($all[$key])) {
                $filters[$key] = $all[$key];
            }
        }

        return $filters;
    }

    private function operator(): AdminUser
    {
        $user = $this->currentUser->user();
        if ($user === null) {
            throw new \RuntimeException('后台操作人取不到');
        }

        return $user;
    }
}
