<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\Notice;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\NoticeRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Notice\NoticeService;

/**
 * 后台系统通知（docs/07 第 3.20 节）：发全员公告、给指定用户发通知、看已发布列表。
 *
 * 两种都打上 `biz_type`（`ANNOUNCE` / `ADMIN_SEND`），「已发布」列表只列这两类 ——
 * 用户自己收到的提现/实名/等级通知不该混进这张表里。
 */
final class AdminNoticeService
{
    /** 一次最多发给多少人（防手滑把整个用户表塞进去）。 */
    private const MAX_RECIPIENTS = 200;

    /** 「已发布」列表认的两种来源。 */
    public const PUBLISHED_BIZ_TYPES = [Notice::BIZ_ANNOUNCE, 'ADMIN_SEND'];

    public function __construct(
        private readonly NoticeRepository $noticeRepository,
        private readonly NoticeService $noticeService,
        private readonly UserRepository $userRepository,
    ) {}

    public function announce(AdminUser $operator, string $title, string $content): void
    {
        $this->assertText($title, $content);
        $this->noticeService->announce($title, $content);
    }

    /**
     * 给指定用户发（可以多个）。
     *
     * 不存在的用户**跳过并报出来**，而不是整个失败 —— 运营贴一串 ID 时，
     * 里面夹一个错的就不该全军覆没（和敏感词批量导入一个思路）。
     *
     * @param array<int,int> $userIds
     * @return array{sent: int, missing: array<int,int>}
     */
    public function sendTo(AdminUser $operator, array $userIds, string $title, string $content): array
    {
        $this->assertText($title, $content);

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if ($userIds === []) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '至少要选一个用户');
        }
        if (count($userIds) > self::MAX_RECIPIENTS) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '一次最多发给 ' . self::MAX_RECIPIENTS . ' 个用户');
        }

        $existing = $this->userRepository->listByIds($userIds)->keys()->map(static fn ($id): int => (int) $id)->all();
        $sent = 0;
        $missing = [];
        foreach ($userIds as $id) {
            if (! in_array($id, $existing, true)) {
                $missing[] = $id;
                continue;
            }

            $this->noticeService->send($id, Notice::CATEGORY_SYSTEM, $title, $content, 'ADMIN_SEND');
            ++$sent;
        }

        return ['sent' => $sent, 'missing' => $missing];
    }

    /**
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function published(int $page, int $pageSize): array
    {
        return $this->noticeRepository->pageByBizTypes(self::PUBLISHED_BIZ_TYPES, $page, $pageSize);
    }

    private function assertText(string $title, string $content): void
    {
        if (trim($title) === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '标题不能为空');
        }
        if (mb_strlen($title) > 64) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '标题最长 64 个字');
        }
    }
}
