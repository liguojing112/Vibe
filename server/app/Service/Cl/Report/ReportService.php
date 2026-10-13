<?php

declare(strict_types=1);

namespace App\Service\Cl\Report;

use App\Exception\ClBusinessException;
use App\Model\Cl\Report;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\ReportRepository;
use App\Repository\Cl\UserRepository;

/**
 * 举报（docs/04 第 3 节、`docs/01` P05 的「…」菜单）。
 *
 * 只做**提交**：处理（判定违规/封号）在后台（T7.5）。
 *
 * 举报会**长期保留被举报的那条消息**（`docs/04` 第 3 节的保留策略：
 * 被 `cl_report.message_id` 关联的消息不参与定时清理），所以带上来的 `message_id`
 * 必须是**举报人自己参与过的**那条 —— 否则等于让任何人能把别人的消息永久钉在库里。
 */
final class ReportService
{
    private const REASONS = [
        Report::REASON_PORN,
        Report::REASON_FRAUD,
        Report::REASON_ABUSE,
        Report::REASON_FAKE,
        Report::REASON_OTHER,
    ];

    /** 最多几张截图（和相册一样 9 张，避免一次塞几百张）。 */
    private const MAX_IMAGES = 9;

    public function __construct(
        private readonly ReportRepository $reportRepository,
        private readonly UserRepository $userRepository,
        private readonly MessageRepository $messageRepository,
    ) {}

    /**
     * @param array<int,string> $images
     * @return array<string,mixed>
     */
    public function submit(
        User $reporter,
        int $targetId,
        string $reason,
        string $content = '',
        array $images = [],
        ?int $messageId = null,
    ): array {
        if ($reporter->id === $targetId) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '不能举报自己');
        }

        $reason = strtoupper(trim($reason));
        if (! \in_array($reason, self::REASONS, true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '举报原因不正确');
        }

        $target = $this->userRepository->findById($targetId);
        if (! $target instanceof User || $target->status === User::STATUS_CANCELLED) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        $content = trim($content);
        if (mb_strlen($content) > 500) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '补充说明最多 500 字');
        }

        $images = array_values(array_filter(array_map(static fn ($url): string => trim((string) $url), $images)));
        if (\count($images) > self::MAX_IMAGES) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '最多上传 ' . self::MAX_IMAGES . ' 张截图');
        }

        if ($messageId !== null && ! $this->messageRepository->belongsToUser($messageId, $reporter->id)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这条消息不在你的会话里');
        }

        /** @var Report $report */
        $report = $this->reportRepository->create([
            'reporter_id' => $reporter->id,
            'target_id' => $targetId,
            'reason' => $reason,
            'content' => $content,
            'images' => $images,
            'message_id' => $messageId,
            'status' => Report::STATUS_PENDING,
        ]);

        return [
            'id' => (int) $report->id,
            'status' => (int) $report->status,
            // 提交后的提示由服务端给，客户端不拼文案
            'status_text' => '举报已提交，我们会尽快处理',
        ];
    }
}
