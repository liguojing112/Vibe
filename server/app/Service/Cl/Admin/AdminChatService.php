<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\Message;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminChatRepository;
use App\Service\Cl\Chat\SensitiveWordService;

/**
 * 后台「聊天管理」里的会话与待审消息（docs/07 第 3.6、3.7 节）。
 *
 * 敏感词库在 `AdminSensitiveWordService`、举报在 `AdminReportService` ——
 * 四件事各管各的，塞一个类里后面没法看。
 */
final class AdminChatService
{
    public function __construct(
        private readonly AdminChatRepository $repository,
        private readonly AdminUserService $userService,
        private readonly SensitiveWordService $sensitiveWordService,
    ) {}

    /**
     * 会话列表。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function conversations(array $filters, int $page, int $pageSize): array
    {
        return $this->repository->conversationPage($filters, $page, $pageSize);
    }

    /** 会话详情：会话本身 + 这一页消息（带价格、免费原因、女方所得积分）。 */
    public function conversationMessages(int $conversationId, int $page, int $pageSize): array
    {
        $conversation = $this->repository->findConversation($conversationId);
        if ($conversation === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会话不存在');
        }

        return [
            'conversation_id' => $conversationId,
            'male_sent_count' => (int) $conversation->male_sent_count,
            'last_message_at' => $conversation->last_message_at?->toDateTimeString(),
        ] + $this->repository->messagePage($conversationId, $page, $pageSize);
    }

    /**
     * 待审消息（命中敏感词的）。
     *
     * 每行带上 `hit_words`（**当前**词库里命中的那几个），界面据此把词标红 ——
     * 消息表上只存了「有没有命中」，没存是哪几个（那是跟着词库变的）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pendingMessages(int $page, int $pageSize): array
    {
        $result = $this->repository->pendingReviewPage($page, $pageSize);

        foreach ($result['list'] as &$row) {
            $row['hit_words'] = $this->sensitiveWordService->matches((string) $row['content']);
        }
        unset($row);

        return $result;
    }

    /** 举报详情里「查看上下文 10 条」。 */
    public function messageContext(int $messageId, int $limit = 5): array
    {
        $message = $this->repository->findMessage($messageId);
        if ($message === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '消息不存在');
        }

        return $this->repository->contextAround((int) $message->conversation_id, $messageId, $limit);
    }

    /**
     * 标记消息：正常 / 违规（docs/07 第 3.7 节）。
     *
     * 违规时可以**顺手封了发送方** —— 那两个动作本来就是一件事
     * （审核员看到违规内容，下一个动作就是封号），分两次点容易漏。
     * 封禁走的是 `AdminUserService::ban()`，所以封禁记录、通知、`account.banned`
     * 推送都和别人封号一模一样，不会出现「从这里封的号收不到通知」。
     *
     * @param array<int,int> $ids
     * @return array{updated: int, banned: bool}
     */
    public function markMessages(
        AdminUser $operator,
        array $ids,
        int $status,
        int $banDays = 0,
        string $banReason = '',
    ): array {
        if ($ids === []) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '没有选中任何消息');
        }
        if (! in_array($status, [Message::REVIEW_NORMAL, Message::REVIEW_VIOLATION], true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '只能标记为正常或违规');
        }

        $senderId = 0;
        if ($status === Message::REVIEW_VIOLATION && $banDays >= 0 && trim($banReason) !== '') {
            // 批量里可能有多条消息、但都来自同一个人（待审那页基本是这种情况）；
            // 万一不是同一个人，就取第一条的发送方 —— 要封谁由审核员在界面上确认过
            $first = $this->repository->findMessage($ids[0]);
            $senderId = $first === null ? 0 : (int) $first->sender_id;
        }

        $updated = $this->repository->markReview($ids, $status);

        if ($senderId > 0) {
            $this->userService->ban($operator, $senderId, $banReason, $banDays);
        }

        return ['updated' => $updated, 'banned' => $senderId > 0];
    }
}
