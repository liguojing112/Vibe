<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClBotChatRuleRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Service\Cl\Admin\AdminBotChatService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation\Delete;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「AI 互聊」：机器人男号给指定女号发消息。
 *
 * 规则只描述「谁发给谁、什么节奏、上限多少」，真正的发送由 `BotChatCrontab` 每分钟驱动。
 * **男号发消息不扣金币，女号的收益只做统计，不进钱包**。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class BotChatController extends AbstractController
{
    public function __construct(
        private readonly AdminBotChatService $service,
        private readonly CurrentUser $currentUser,
    ) {}

    #[Get(
        path: '/admin/cl/bot-chat/list',
        operationId: 'clBotChatList',
        summary: 'AI 互聊规则列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:list')]
    #[ResultResponse(new Result())]
    public function list(RequestInterface $request): Result
    {
        return $this->success($this->service->list(
            [
                'keyword' => $request->input('keyword'),
                'enabled' => $request->input('enabled'),
            ],
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Get(
        path: '/admin/cl/bot-chat/{id}',
        operationId: 'clBotChatDetail',
        summary: '规则详情',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:list')]
    #[ResultResponse(new Result())]
    public function detail(int $id): Result
    {
        return $this->success($this->service->detail($id));
    }

    #[Post(
        path: '/admin/cl/bot-chat/save',
        operationId: 'clBotChatSave',
        summary: '新建/保存规则',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:edit')]
    #[ResultResponse(new Result())]
    public function save(ClBotChatRuleRequest $request): Result
    {
        $id = (int) $request->input('id', 0);

        $saved = $this->service->save(
            $id > 0 ? $id : null,
            $request->validated(),
            $this->currentUser->id(),
        );

        return $this->success(['id' => $saved]);
    }

    #[Post(
        path: '/admin/cl/bot-chat/global-toggle',
        operationId: 'clBotChatGlobalToggle',
        summary: 'AI 互聊总开关',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:edit')]
    #[ResultResponse(new Result())]
    public function globalToggle(RequestInterface $request): Result
    {
        $enabled = (int) $request->input('enabled', 0) === 1;
        $this->service->setGlobalEnabled($enabled);

        return $this->success(['enabled' => $enabled ? 1 : 0]);
    }

    #[Post(
        path: '/admin/cl/bot-chat/{id}/toggle',
        operationId: 'clBotChatToggle',
        summary: '启动/暂停规则',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:edit')]
    #[ResultResponse(new Result())]
    public function toggle(int $id, RequestInterface $request): Result
    {
        $this->service->toggle($id, (int) $request->input('enabled', 1) === 1);

        return $this->success();
    }

    #[Delete(
        path: '/admin/cl/bot-chat/{id}',
        operationId: 'clBotChatDelete',
        summary: '删除规则',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:edit')]
    #[ResultResponse(new Result())]
    public function delete(int $id): Result
    {
        $this->service->delete($id);

        return $this->success();
    }

    #[Get(
        path: '/admin/cl/bot-chat/{id}/targets',
        operationId: 'clBotChatTargets',
        summary: '目标女号明细',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:list')]
    #[ResultResponse(new Result())]
    public function targets(int $id): Result
    {
        return $this->success($this->service->targets($id, $this->getCurrentPage(), $this->getPageSize()));
    }

    #[Get(
        path: '/admin/cl/bot-chat/{id}/logs',
        operationId: 'clBotChatLogs',
        summary: '发送记录',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:list')]
    #[ResultResponse(new Result())]
    public function logs(int $id, RequestInterface $request): Result
    {
        return $this->success($this->service->logs(
            $id,
            [
                'female_id' => $request->input('female_id'),
                'status' => $request->input('status'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            $this->getCurrentPage(),
            $this->getPageSize(),
        ));
    }

    #[Get(
        path: '/admin/cl/bot-chat/search-male',
        operationId: 'clBotChatSearchMale',
        summary: '搜索男号（下拉用）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:list')]
    #[ResultResponse(new Result())]
    public function searchMale(RequestInterface $request): Result
    {
        return $this->success($this->service->searchMales(
            (string) $request->input('keyword', ''),
            min(50, max(1, (int) $request->input('limit', 20))),
        ));
    }

    #[Get(
        path: '/admin/cl/bot-chat/search-female',
        operationId: 'clBotChatSearchFemale',
        summary: '搜索女号（下拉用）',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 内容管理']
    )]
    #[Permission(code: 'cl:content:bot:list')]
    #[ResultResponse(new Result())]
    public function searchFemale(RequestInterface $request): Result
    {
        return $this->success($this->service->searchFemales(
            (string) $request->input('keyword', ''),
            min(50, max(1, (int) $request->input('limit', 20))),
        ));
    }
}
