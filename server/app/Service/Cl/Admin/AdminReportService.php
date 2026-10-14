<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\Report;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\ReportRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Notice\NoticeService;

/**
 * 后台举报处理（docs/07 第 3.9 节）。
 *
 * 「判定违规」和「封号」在界面上是两个勾选框，但**审完之后要通知举报人** ——
 * 举报了没下文，下次就没人举报了；所以这两件事在这里一起做完。
 *
 * 封禁走 `AdminUserService::ban()`（和用户管理那边同一个入口），
 * 封禁记录、站内通知、`account.banned` 推送一个都不会少。
 */
final class AdminReportService
{
    /** 举报原因的中文（和 `01` P05 弹窗上的选项一致）。 */
    private const REASON_LABELS = [
        Report::REASON_PORN => '色情低俗',
        Report::REASON_FRAUD => '广告诈骗',
        Report::REASON_ABUSE => '辱骂骚扰',
        Report::REASON_FAKE => '虚假资料',
        Report::REASON_OTHER => '其他',
    ];

    public function __construct(
        private readonly ReportRepository $repository,
        private readonly UserRepository $userRepository,
        private readonly NoticeService $noticeService,
        private readonly AdminUserService $userService,
    ) {}

    /**
     * 举报列表。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function list(int $status, int $page, int $pageSize): array
    {
        $result = $this->repository->pageByStatus($status, $page, $pageSize);

        foreach ($result['list'] as &$row) {
            $row['reason_label'] = self::REASON_LABELS[$row['reason']] ?? (string) $row['reason'];
            $row['status_text'] = $row['status'] === Report::STATUS_VIOLATION
                ? '已判定违规'
                : ($row['status'] === Report::STATUS_INNOCENT ? '未发现违规' : '待处理');
        }
        unset($row);

        return $result;
    }

    /**
     * 处理一条举报。
     *
     * @param bool $violation true = 判定违规，false = 判定不违规
     * @param int $banDays 判定违规时如果填了原因就顺手封号（0 = 永久）
     */
    public function handle(
        AdminUser $operator,
        int $id,
        bool $violation,
        string $remark,
        int $banDays = 0,
        string $banReason = '',
    ): void {
        $report = $this->repository->findById($id);
        if (! $report instanceof Report) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '举报不存在');
        }
        if ((int) $report->status !== Report::STATUS_PENDING) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这条举报已经处理过了');
        }

        $remark = trim($remark);
        $target = $this->userRepository->findById((int) $report->target_id);
        if (! $target instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        $this->repository->saveById($id, [
            'status' => $violation ? Report::STATUS_VIOLATION : Report::STATUS_INNOCENT,
            'handle_result' => $remark,
            'handler_id' => $operator->id,
            'handled_at' => date('Y-m-d H:i:s'),
        ]);

        // 判定违规 + 填了封禁原因 → 顺手封（用同一个封禁入口）
        $banReason = trim($banReason);
        if ($violation && $banReason !== '') {
            $this->userService->ban($operator, (int) $target->id, $banReason, $banDays);
        }

        $this->noticeService->reportHandled((int) $report->reporter_id, (string) $target->nickname, $violation, $remark);
    }
}
