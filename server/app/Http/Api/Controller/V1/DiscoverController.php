<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\DiscoverRequest;
use App\Model\Cl\User;
use App\Repository\Cl\DiscoverRepository;
use App\Service\Cl\Discover\DiscoverService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Swagger\Annotation as OA;
use Hyperf\Validation\ValidationException;

/**
 * 发现页（docs/05 第 4.3 节）。要登录。
 *
 * 「广场」就是这个页面的数据，不另开接口（客户 2026-10-11 确认）。
 */
#[OA\HyperfServer(name: 'http')]
final class DiscoverController
{
    public function __construct(
        private readonly DiscoverService $discoverService,
    ) {}

    #[OA\Get(
        path: '/api/v1/discover',
        operationId: 'clDiscover',
        summary: '发现列表（只返回异性）',
        tags: ['app:discover']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function index(DiscoverRequest $request): ApiResult
    {
        $result = $this->discoverService->list($this->currentUser(), $this->filters($request), $this->page($request));

        return ApiResult::page(
            $result['list'],
            $result['total'],
            $result['page'],
            DiscoverRepository::PAGE_SIZE,
        );
    }

    #[OA\Get(
        path: '/api/v1/discover/search',
        operationId: 'clDiscoverSearch',
        summary: '按昵称或 ID 搜异性',
        tags: ['app:discover']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function search(DiscoverRequest $request): ApiResult
    {
        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword === '') {
            throw ValidationException::withMessages(['keyword' => ['请输入关键词']]);
        }

        $result = $this->discoverService->search($this->currentUser(), $keyword, $this->page($request));

        return ApiResult::page(
            $result['list'],
            $result['total'],
            $result['page'],
            DiscoverRepository::PAGE_SIZE,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function filters(RequestInterface $request): array
    {
        return [
            'tab' => (string) $request->input('tab', 'recommend'),
            'city' => $request->input('city'),
            'age_min' => $request->input('age_min'),
            'age_max' => $request->input('age_max'),
            'purpose' => (array) $request->input('purpose', []),
            'verified' => $request->input('verified'),
            'complete' => $request->input('complete'),
            'online_only' => $request->input('online_only'),
        ];
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
