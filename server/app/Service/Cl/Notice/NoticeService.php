<?php

declare(strict_types=1);

namespace App\Service\Cl\Notice;

use App\Model\Cl\Notice;
use App\Repository\Cl\NoticeRepository;

/**
 * 系统通知（docs/04 第 6 节）。
 *
 * T6.1 只需要「发一条」这一条路（升级通知）：列表、已读、`notice.new` 推送在 T6.4 补齐。
 *
 * **不吞异常**：发通知和它要通知的那件事写在**同一个事务**里（升级 → 通知），
 * 所以「等级升上去了但通知没发出来」这种事不会发生。
 * 跨进程的推送（`PushService`）才需要吞异常 —— 那个失败了业务照样得成。
 */
final class NoticeService
{
    public function __construct(
        private readonly NoticeRepository $noticeRepository,
    ) {}

    /**
     * 发给某一个人（P10 的「系统消息 / 互动通知」里能看到）。
     *
     * @param int $category [Notice::CATEGORY_SYSTEM] / [Notice::CATEGORY_INTERACT]
     */
    public function send(
        int $userId,
        int $category,
        string $title,
        string $content = '',
        ?string $bizType = null,
        ?int $bizId = null,
    ): Notice {
        /** @var Notice $notice */
        $notice = $this->noticeRepository->create([
            'user_id' => $userId,
            'category' => $category,
            'title' => $title,
            'content' => $content,
            'biz_type' => $bizType,
            'biz_id' => $bizId,
        ]);

        return $notice;
    }

    /** 全员公告（`user_id = 0`，已读状态存 `cl_notice_read`）。 */
    public function announce(string $title, string $content = ''): Notice
    {
        return $this->send(Notice::ALL_USERS, Notice::CATEGORY_SYSTEM, $title, $content, Notice::BIZ_ANNOUNCE);
    }
}
