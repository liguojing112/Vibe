<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClAiPersonaRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Service\Cl\Admin\AdminAiChatService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「AI 代聊」（只对女用户开放）。
 *
 * 运营在这里给女号配人设、开自动回复、设主动程度和活跃时段。
 * 关掉开关后对方发消息就不会再自动回了。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class AiChatController extends AbstractController
{
    public function __construct(
        private readonly AdminAiChatService $aiChatService,
    ) {}

    #[Get(
        path: '/admin/cl/ai/list',
        operationId: 'clAiList',
        summary: '女用户 AI 代聊列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:ai:list')]
    #[ResultResponse(new Result())]
    public function list(RequestInterface $request): Result
    {
        return $this->success($this->aiChatService->list(
            [
                'keyword' => $request->input('keyword'),
                'enabled' => $request->input('enabled'),
            ],
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Get(
        path: '/admin/cl/ai/{id}',
        operationId: 'clAiDetail',
        summary: '读取某个人设',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:ai:list')]
    #[ResultResponse(new Result())]
    public function detail(int $id): Result
    {
        return $this->success($this->aiChatService->detail($id));
    }

    #[Post(
        path: '/admin/cl/ai/{id}/save',
        operationId: 'clAiSave',
        summary: '保存人设',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:ai:edit')]
    #[ResultResponse(new Result())]
    public function save(int $id, ClAiPersonaRequest $request): Result
    {
        $this->aiChatService->save($id, $request->validated());

        return $this->success();
    }

    #[Post(
        path: '/admin/cl/ai/{id}/toggle',
        operationId: 'clAiToggle',
        summary: '快速开关自动回复',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:ai:edit')]
    #[ResultResponse(new Result())]
    public function toggle(int $id, RequestInterface $request): Result
    {
        $this->aiChatService->toggle($id, (int) $request->input('enabled', 1) === 1);

        return $this->success();
    }
}
