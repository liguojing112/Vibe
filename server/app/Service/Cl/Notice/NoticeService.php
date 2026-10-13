<?php

declare(strict_types=1);

namespace App\Service\Cl\Notice;

use App\Model\Cl\Notice;
use App\Model\Cl\User;
use App\Repository\Cl\NoticeReadRepository;
use App\Repository\Cl\NoticeRepository;
use App\Service\Cl\Push\PushService;

/**
 * 系统通知与互动通知（docs/04 第 6 节，P10）。
 *
 * **所有通知都从 `send()` 出去**（各业务事件调它），这样「发通知」只有一条路：
 * 升级、实名结果、提现结果、资料审核结果、封号、被关注、收到礼物。
 *
 * 推送就写在 `send()` 里，**不等事务提交**：
 * `notice.new` 的载荷是自包含的（标题正文都在里面），客户端不用回查就能直接显示，
 * 所以「推送比提交早几毫秒」不会让用户看到错的东西；
 * 反过来把推送交给每个调用方，7 个业务事件都要各自记得推一次，更容易漏。
 */
final class NoticeService
{
    /** P10 每页条数（docs/05 第 1 节分页约定 20）。 */
    public const PAGE_SIZE = 20;

    public function __construct(
        private readonly NoticeRepository $noticeRepository,
        private readonly NoticeReadRepository $noticeReadRepository,
        private readonly PushService $pushService,
    ) {}

    /**
     * 发给某一个人（P10 的「系统消息 / 互动通知」里能看到），并推一条 `notice.new`。
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

        $this->pushService->noticeNew($notice);

        return $notice;
    }

    /** 全员公告（`user_id = 0`，已读状态存 `cl_notice_read`）。**不推送**（没有具体收件人）。 */
    public function announce(string $title, string $content = ''): Notice
    {
        return $this->send(Notice::ALL_USERS, Notice::CATEGORY_SYSTEM, $title, $content, Notice::BIZ_ANNOUNCE);
    }

    // ---------- 业务事件的快捷方式（文案统一在这里拼，别处只传人和东西） ----------

    /** 有人关注了你（互动通知）。 */
    public function followed(User $target, User $follower): Notice
    {
        return $this->send(
            $target->id,
            Notice::CATEGORY_INTERACT,
            '有人关注了你',
            sprintf('%s 关注了你，去看看吧', $follower->nickname),
            'FOLLOW',
            $follower->id,
        );
    }

    /** 收到礼物（互动通知）。 */
    public function giftReceived(User $receiver, User $sender, string $giftName): Notice
    {
        return $this->send(
            $receiver->id,
            Notice::CATEGORY_INTERACT,
            '收到礼物',
            sprintf('%s 送了你「%s」', $sender->nickname, $giftName),
            'GIFT',
            $sender->id,
        );
    }

    /** 资料审核结果（头像/昵称/简介/相册）。后台审核动作在 T7，届时调这个方法。 */
    public function profileReviewed(int $userId, bool $passed, string $fieldLabel, ?string $reason = null): Notice
    {
        return $this->send(
            $userId,
            Notice::CATEGORY_SYSTEM,
            $passed ? '资料审核通过' : '资料审核未通过',
            $passed
                ? sprintf('你修改的%s已通过审核', $fieldLabel)
                : sprintf('你修改的%s未通过审核：%s', $fieldLabel, (string) $reason),
            'PROFILE_AUDIT',
        );
    }

    /** 封号（后台封禁动作在 T7，届时调这个方法）。 */
    public function banned(int $userId, string $reason, ?int $banDays = null): Notice
    {
        return $this->send(
            $userId,
            Notice::CATEGORY_SYSTEM,
            '账号已被封禁',
            $banDays === null
                ? sprintf('你的账号已被永久封禁：%s', $reason)
                : sprintf('你的账号已被封禁 %d 天：%s', $banDays, $reason),
            'BAN',
        );
    }

    // ---------- 列表与已读（P10） ----------

    /**
     * `/notice/list`：某个分类的通知，**调用后该分类标为已读**（docs/05 第 4.9 节）。
     *
     * 列表里既有「发给我的」也有「全员公告」（后者已读状态存在 `cl_notice_read`）。
     *
     * @return array<string,mixed>
     */
    public function list(User $user, int $category, int $page): array
    {
        $offset = max(0, ($page - 1) * self::PAGE_SIZE);
        $rows = $this->noticeRepository->pageForUser($user->id, $category, $offset, self::PAGE_SIZE);

        // 公告的已读状态不在通知行上，要单独查一次（一对一的直接看 read_at）
        $announcementIds = [];
        foreach ($rows as $notice) {
            if ($notice->user_id === Notice::ALL_USERS) {
                $announcementIds[] = (int) $notice->id;
            }
        }
        $readAnnouncements = $this->noticeReadRepository->readNoticeIds($user->id, $announcementIds);

        // 先取列表再标已读：返回的 `read` 是「打开之前读没读过」，页面据此决定要不要画红点
        $items = [];
        foreach ($rows as $notice) {
            $items[] = [
                'id' => (int) $notice->id,
                'category' => (int) $notice->category,
                'title' => (string) $notice->title,
                'content' => (string) $notice->content,
                'biz_type' => $notice->biz_type,
                'biz_id' => $notice->biz_id === null ? null : (int) $notice->biz_id,
                'read' => $notice->user_id === Notice::ALL_USERS
                    ? \in_array((int) $notice->id, $readAnnouncements, true)
                    : $notice->read_at !== null,
                'created_at' => $notice->created_at?->toDateTimeString(),
            ];
        }

        $this->markCategoryRead($user->id, $category);

        return [
            'list' => $items,
            'total' => $this->noticeRepository->countForUser($user->id, $category),
            'page' => $page,
            'unread' => $this->noticeRepository->unreadCountFor($user->id),
        ];
    }

    /** 未读数（底部角标用，和 `/chat/unread` 的 `notice` 一个口径）。 */
    public function unreadCount(int $userId): int
    {
        return $this->noticeRepository->unreadCountFor($userId);
    }

    /**
     * 把一个分类标成已读：一对一的写 `read_at`，全员公告往 `cl_notice_read` 补记录。
     */
    public function markCategoryRead(int $userId, int $category): void
    {
        $this->noticeRepository->markMineRead($userId, $category);
        $this->noticeReadRepository->markRead(
            $userId,
            $this->noticeRepository->unreadAnnouncementIds($userId, $category),
        );
    }
}
