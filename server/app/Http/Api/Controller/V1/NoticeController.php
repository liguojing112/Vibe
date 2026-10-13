<?php

declare(strict_types=1);

namespace App\Http\Api\Controller\V1;

use App\Http\Api\ApiResult;
use App\Http\Api\Middleware\AppAuthMiddleware;
use App\Http\Api\Request\V1\NoticeListRequest;
use App\Http\Api\Request\V1\ReportRequest;
use App\Model\Cl\User;
use App\Service\Cl\Notice\NoticeService;
use App\Service\Cl\Report\ReportService;
use Hyperf\Context\Context;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Swagger\Annotation as OA;

/**
 * 系统消息与举报（docs/05 第 4.9 节，P10 / P05 的举报入口）。
 *
 * 通知列表**打开就标已读**（docs/05 原文），举报只提交、处理在后台（T7.5）。
 */
#[OA\HyperfServer(name: 'http')]
final class NoticeController
{
    public function __construct(
        private readonly NoticeService $noticeService,
        private readonly ReportService $reportService,
    ) {}

    #[OA\Get(
        path: '/api/v1/notice/list',
        operationId: 'clNoticeList',
        summary: '系统消息 / 互动通知（调用后该分类标为已读）',
        tags: ['app:notice']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function list(NoticeListRequest $request): ApiResult
    {
        return ApiResult::success($this->noticeService->list(
            $this->currentUser(),
            (int) $request->input('category'),
            (int) $request->input('page', 1),
        ));
    }

    #[OA\Post(
        path: '/api/v1/report',
        operationId: 'clReport',
        summary: '举报（原因必填，说明和截图可选）',
        tags: ['app:notice']
    )]
    #[Middleware(AppAuthMiddleware::class)]
    public function report(ReportRequest $request): ApiResult
    {
        $messageId = $request->input('message_id');
        /** @var array<int,string> $images */
        $images = (array) $request->input('images', []);

        return ApiResult::success($this->reportService->submit(
            $this->currentUser(),
            (int) $request->input('target_id'),
            (string) $request->input('reason'),
            (string) $request->input('content', ''),
            $images,
            $messageId === null ? null : (int) $messageId,
        ));
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Context::get('cl_user');

        return $user;
    }
}
