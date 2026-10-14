<?php

declare(strict_types=1);

namespace App\Http\Admin\Controller\Cl;

use App\Http\Admin\Controller\AbstractController;
use App\Http\Admin\Middleware\PermissionMiddleware;
use App\Http\Admin\Request\Cl\ClNoticeRequest;
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Http\Common\Middleware\OperationMiddleware;
use App\Http\Common\Result;
use App\Http\CurrentUser;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminNoticeService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation\Get;
use Hyperf\Swagger\Annotation\HyperfServer;
use Hyperf\Swagger\Annotation\Post;
use Mine\Access\Attribute\Permission;
use Mine\Swagger\Attributes\ResultResponse;

/**
 * 后台「系统通知」（docs/07 第 3.20 节）。
 *
 * 客服也有这个权限码（`cl:operation:notice`）—— 通知是运营和客服的日常动作，
 * 但改配置不是，所以那一块仍然是超管的。
 */
#[HyperfServer(name: 'http')]
#[Middleware(middleware: AccessTokenMiddleware::class, priority: 100)]
#[Middleware(middleware: PermissionMiddleware::class, priority: 99)]
#[Middleware(middleware: OperationMiddleware::class, priority: 98)]
final class NoticeController extends AbstractController
{
    public function __construct(
        private readonly AdminNoticeService $noticeService,
        private readonly CurrentUser $currentUser,
    ) {}

    /**
     * 发通知：不传 `user_ids` 就是全员公告，传了就是定向发送。
     *
     * 两个动作共用一个接口，因为界面上本来就是「发给谁」的一个选择。
     */
    #[Post(
        path: '/admin/cl/notice/publish',
        operationId: 'clNoticePublish',
        summary: '发布通知/公告',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    #[Permission(code: 'cl:operation:notice')]
    #[ResultResponse(new Result())]
    public function publish(ClNoticeRequest $request): Result
    {
        $data = $request->validated();
        $title = (string) $data['title'];
        $content = (string) ($data['content'] ?? '');
        $userIds = array_map('intval', $data['user_ids'] ?? []);

        if ($userIds === []) {
            $this->noticeService->announce($this->operator(), $title, $content);

            return $this->success(['mode' => 'announce']);
        }

        return $this->success(['mode' => 'direct'] + $this->noticeService->sendTo($this->operator(), $userIds, $title, $content));
    }

    #[Get(
        path: '/admin/cl/notice/list',
        operationId: 'clNoticeList',
        summary: '已发布列表',
        security: [['Bearer' => [], 'ApiKey' => []]],
        tags: ['畅撩交友 / 运营配置']
    )]
    #[Permission(code: 'cl:operation:notice')]
    #[ResultResponse(new Result())]
    public function list(): Result
    {
        return $this->success($this->noticeService->published($this->getCurrentPage(), $this->getPageSize()));
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
