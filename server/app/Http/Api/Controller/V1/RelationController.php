<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\RelationTargetRequest;
use App\Model\Cl\User;
use App\Service\Cl\Relation\RelationService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;

/**
 * App 关系接口（docs/05 第 4.4 节）：关注、拉黑、访客、粉丝、好友、黑名单。全部要登录。
 *
 * 列表统一返回分页外壳 `{list, total, page, page_size, has_more}`，另外多一个 `locked`：
 * **喜欢我的人 / 最近访客** 在非会员（男用户，或女用户且开关关掉）时打码，`locked = true`。
 */
#[OA\HyperfServer(name: 'http')]
final class RelationController
{
    public function __construct(
        private readonly RelationService $relationService,
    ) {}

    #[OA\Post(
        path: '/api/v1/relation/follow',
        operationId: 'clRelationFollow',
        summary: '关注',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function follow(RelationTargetRequest $request): ApiResult
    {
        $this->relationService->follow($this->currentUser(), (int) $request->input('target_id'));

        return ApiResult::success();
    }

    #[OA\Post(
        path: '/api/v1/relation/unfollow',
        operationId: 'clRelationUnfollow',
        summary: '取消关注',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function unfollow(RelationTargetRequest $request): ApiResult
    {
        $this->relationService->unfollow($this->currentUser(), (int) $request->input('target_id'));

        return ApiResult::success();
    }

    #[OA\Post(
        path: '/api/v1/relation/block',
        operationId: 'clRelationBlock',
        summary: '拉黑',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function block(RelationTargetRequest $request): ApiResult
    {
        $this->relationService->block($this->currentUser(), (int) $request->input('target_id'));

        return ApiResult::success();
    }

    #[OA\Post(
        path: '/api/v1/relation/unblock',
        operationId: 'clRelationUnblock',
        summary: '解除拉黑',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function unblock(RelationTargetRequest $request): ApiResult
    {
        $this->relationService->unblock($this->currentUser(), (int) $request->input('target_id'));

        return ApiResult::success();
    }

    #[OA\Get(
        path: '/api/v1/relation/following',
        operationId: 'clRelationFollowing',
        summary: '我关注的',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function following(RequestInterface $request): ApiResult
    {
        return $this->pageResponse($this->relationService->following($this->currentUser(), $this->page($request)), false);
    }

    #[OA\Get(
        path: '/api/v1/relation/friends',
        operationId: 'clRelationFriends',
        summary: '互关（好友）',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function friends(RequestInterface $request): ApiResult
    {
        return $this->pageResponse($this->relationService->friends($this->currentUser(), $this->page($request)), false);
    }

    #[OA\Get(
        path: '/api/v1/relation/fans',
        operationId: 'clRelationFans',
        summary: '喜欢我的人（非会员打码）',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function fans(RequestInterface $request): ApiResult
    {
        $user = $this->currentUser();

        return $this->pageResponse(
            $this->relationService->fans($user, $this->page($request)),
            $this->relationService->shouldMask($user),
        );
    }

    #[OA\Get(
        path: '/api/v1/relation/visitors',
        operationId: 'clRelationVisitors',
        summary: '最近访客（读一次就把未读清零）',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function visitors(RequestInterface $request): ApiResult
    {
        $user = $this->currentUser();

        return $this->pageResponse(
            $this->relationService->visitors($user, $this->page($request)),
            $this->relationService->shouldMask($user),
        );
    }

    #[OA\Get(
        path: '/api/v1/relation/blocks',
        operationId: 'clRelationBlocks',
        summary: '黑名单',
        tags: ['app:relation']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function blocks(RequestInterface $request): ApiResult
    {
        return $this->pageResponse($this->relationService->blocks($this->currentUser(), $this->page($request)), false);
    }

    /**
     * @param array{list: array<int,array<string,mixed>>, total: int, page: int} $result
     */
    private function pageResponse(array $result, bool $locked): ApiResult
    {
        $total = $result['total'];
        $page = $result['page'];

        return ApiResult::success([
            'list' => $result['list'],
            'total' => $total,
            'page' => $page,
            'page_size' => RelationService::PAGE_SIZE,
            'has_more' => $page * RelationService::PAGE_SIZE < $total,
            'locked' => $locked,
        ]);
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
