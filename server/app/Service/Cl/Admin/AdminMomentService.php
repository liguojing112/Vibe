<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Model\Cl\Moment;
use App\Model\Cl\User;
use App\Repository\Cl\MomentCommentRepository;
use App\Repository\Cl\MomentRepository;
use App\Repository\Cl\UserRepository;

/**
 * 后台「朋友圈管理」：看列表、隐藏/恢复、删除、看评论。
 *
 * 和 App 端不同，这里**不过滤 status**（隐藏的也要能看见、能恢复），
 * 列表里补上作者昵称，免得运营对着 user_id 猜是谁。
 */
final class AdminMomentService
{
    public function __construct(
        private readonly MomentRepository $momentRepository,
        private readonly MomentCommentRepository $commentRepository,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * @param array<string,mixed> $filters
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function list(array $filters, int $page, int $pageSize): array
    {
        $offset = max(0, ($page - 1) * $pageSize);
        $result = $this->momentRepository->pageForAdmin($filters, $offset, $pageSize);

        $userIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['user_id'],
            $result['list'],
        )));
        $users = $userIds === [] ? [] : $this->userRepository->listByIds($userIds)->keyBy('id');

        $list = [];
        foreach ($result['list'] as $row) {
            $author = $users[$row['user_id']] ?? null;
            $row['nickname'] = $author instanceof User ? $author->nickname : '';
            $row['images'] = is_string($row['images'] ?? null)
                ? (json_decode($row['images'], true) ?: [])
                : ($row['images'] ?? []);
            $list[] = $row;
        }

        return ['list' => $list, 'total' => $result['total']];
    }

    /** 1 = 正常、2 = 隐藏。隐藏只是让 App 端看不到，数据还在。 */
    public function setStatus(int $id, int $status): void
    {
        $this->momentRepository->updateById($id, ['status' => $status]);
    }

    public function remove(int $id): void
    {
        $this->momentRepository->softDelete($id);
    }

    /**
     * 某条动态的评论（后台看得见全部，含被隐藏的）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function comments(int $momentId, int $page, int $pageSize): array
    {
        $offset = max(0, ($page - 1) * $pageSize);
        $rows = $this->commentRepository->pageByMoment($momentId, $offset, $pageSize);
        $total = $this->commentRepository->countByMoment($momentId);

        $userIds = array_values(array_unique(array_map(
            static fn ($row): int => (int) $row->user_id,
            $rows,
        )));
        $users = $userIds === [] ? [] : $this->userRepository->listByIds($userIds)->keyBy('id');

        $list = [];
        foreach ($rows as $row) {
            $author = $users[$row->user_id] ?? null;
            $list[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'nickname' => $author instanceof User ? $author->nickname : '',
                'content' => (string) $row->content,
                'created_at' => $row->created_at->format('Y-m-d H:i:s'),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /** 方便前端显示状态文案。 */
    public static function statusText(int $status): string
    {
        return $status === Moment::STATUS_HIDDEN ? '已隐藏' : '正常';
    }
}
