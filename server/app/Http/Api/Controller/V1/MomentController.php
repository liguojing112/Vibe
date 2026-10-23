<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\CreateMomentCommentRequest;
use App\Http\Api\Request\V1\PublishMomentRequest;
use App\Model\Cl\User;
use App\Service\Cl\Moment\MomentService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;

/**
 * 朋友圈（动态）。全部要登录。
 *
 * 注意 `/moment/mine` 和 `/moment/{id}` 同是 GET：FastRoute 优先命中静态段，
 * 所以 `mine` 不会被当成 id 吃掉。
 */
#[OA\HyperfServer(name: 'http')]
final class MomentController
{
    public function __construct(
        private readonly MomentService $momentService,
    ) {}

    #[OA\Get(
        path: '/api/v1/moment/user/{id}',
        operationId: 'clMomentUserList',
        summary: '某个用户的动态列表',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function userMoments(RequestInterface $request): ApiResult
    {
        $result = $this->momentService->userMoments(
            $this->currentUser(),
            (int) $request->route('id'),
            $this->page($request),
        );

        return $this->pageResult($result);
    }

    #[OA\Get(
        path: '/api/v1/moment/mine',
        operationId: 'clMomentMine',
        summary: '我的动态',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function mine(RequestInterface $request): ApiResult
    {
        return $this->pageResult($this->momentService->mine($this->currentUser(), $this->page($request)));
    }

    #[OA\Post(
        path: '/api/v1/moment',
        operationId: 'clMomentPublish',
        summary: '发布动态',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function publish(PublishMomentRequest $request): ApiResult
    {
        return ApiResult::success($this->momentService->publish(
            $this->currentUser(),
            (string) $request->input('content', ''),
            (array) $request->input('images', []),
        ));
    }

    #[OA\Delete(
        path: '/api/v1/moment/{id}',
        operationId: 'clMomentRemove',
        summary: '删除自己的动态',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function remove(RequestInterface $request): ApiResult
    {
        $this->momentService->remove($this->currentUser(), (int) $request->route('id'));

        return ApiResult::success();
    }

    #[OA\Post(
        path: '/api/v1/moment/{id}/like',
        operationId: 'clMomentLike',
        summary: '点赞',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function like(RequestInterface $request): ApiResult
    {
        return ApiResult::success($this->momentService->like(
            $this->currentUser(),
            (int) $request->route('id'),
        ));
    }

    #[OA\Delete(
        path: '/api/v1/moment/{id}/like',
        operationId: 'clMomentUnlike',
        summary: '取消点赞',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function unlike(RequestInterface $request): ApiResult
    {
        return ApiResult::success($this->momentService->unlike(
            $this->currentUser(),
            (int) $request->route('id'),
        ));
    }

    #[OA\Get(
        path: '/api/v1/moment/{id}/comment',
        operationId: 'clMomentComments',
        summary: '评论列表',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function comments(RequestInterface $request): ApiResult
    {
        $result = $this->momentService->commentPage(
            $this->currentUser(),
            (int) $request->route('id'),
            $this->page($request),
        );

        return $this->pageResult($result);
    }

    #[OA\Post(
        path: '/api/v1/moment/{id}/comment',
        operationId: 'clMomentComment',
        summary: '发评论',
        tags: ['app:moment']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function comment(CreateMomentCommentRequest $request): ApiResult
    {
        return ApiResult::success($this->momentService->addComment(
            $this->currentUser(),
            (int) $request->route('id'),
            (string) $request->input('content', ''),
        ));
    }

    /**
     * @param array{list: array<int,array<string,mixed>>, total: int, page: int} $result
     */
    private function pageResult(array $result): ApiResult
    {
        return ApiResult::page($result['list'], $result['total'], $result['page'], MomentService::PAGE_SIZE);
    }

    private function page(RequestInterface $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
