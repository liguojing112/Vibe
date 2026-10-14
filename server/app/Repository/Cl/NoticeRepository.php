<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Notice;
use App\Repository\IRepository;
use Hyperf\Carbon\Carbon;

/**
 * @extends IRepository<Notice>
 */
final class NoticeRepository extends IRepository
{
    public function __construct(
        protected readonly Notice $model
    ) {}

    /**
     * 某个用户某分类下的通知（P10 的两个 Tab）。
     *
     * 可见的 = **发给我的**（`user_id` = 我）+ **全员公告**（`user_id` = 0）。
     *
     * @return array<int,Notice>
     */
    public function pageForUser(int $userId, int $category, int $offset, int $limit): array
    {
        /** @var array<int,Notice> $rows */
        $rows = $this->model->newQuery()
            ->whereIn('user_id', [$userId, Notice::ALL_USERS])
            ->where('category', $category)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    public function countForUser(int $userId, int $category): int
    {
        return $this->model->newQuery()
            ->whereIn('user_id', [$userId, Notice::ALL_USERS])
            ->where('category', $category)
            ->count();
    }

    /**
     * 未读数（底部角标 / P10 的红点）。
     *
     * 两部分：**发给我的、还没读的** + **全员公告里我还没读过的**。
     * 公告的已读状态在 `cl_notice_read`，所以要用一个 `NOT EXISTS` 子查询。
     *
     * @param null|int $category 传 null 表示所有分类合计
     */
    public function unreadCountFor(int $userId, ?int $category = null): int
    {
        $query = $this->model->newQuery()
            ->where(static function ($q) use ($userId): void {
                // 一对一：收件人是我且没读过
                $q->where(static function ($sub) use ($userId): void {
                    $sub->where('user_id', $userId)->whereNull('read_at');
                })
                    // 全员公告：我没在 cl_notice_read 里留过记录
                    ->orWhere(static function ($sub) use ($userId): void {
                        $sub->where('user_id', Notice::ALL_USERS)
                            ->whereRaw(
                                'NOT EXISTS (SELECT 1 FROM cl_notice_read WHERE cl_notice_read.notice_id = cl_notice.id AND cl_notice_read.user_id = ?)',
                                [$userId],
                            );
                    });
            });

        if ($category !== null) {
            $query->where('category', $category);
        }

        return $query->count();
    }

    /**
     * 把这个分类里「发给我的」未读通知标成已读（P10：打开列表就标已读）。
     *
     * @return int 影响的行数
     */
    public function markMineRead(int $userId, int $category): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('category', $category)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * 这个分类里我**还没读过**的全员公告 id（要往 `cl_notice_read` 里补记录）。
     *
     * @return array<int,int>
     */
    public function unreadAnnouncementIds(int $userId, int $category): array
    {
        return $this->model->newQuery()
            ->where('user_id', Notice::ALL_USERS)
            ->where('category', $category)
            ->whereRaw(
                'NOT EXISTS (SELECT 1 FROM cl_notice_read WHERE cl_notice_read.notice_id = cl_notice.id AND cl_notice_read.user_id = ?)',
                [$userId],
            )
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * 后台「已发布」列表（docs/07 第 3.20 节）：只列**后台从这里发出去的**那些
     * （全员公告 + 定向发送），用户正常的系统通知不混进来。
     *
     * @param array<int,string> $bizTypes
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pageByBizTypes(array $bizTypes, int $page, int $pageSize): array
    {
        $query = $this->model->newQuery()->whereIn('biz_type', $bizTypes);

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->offset(($page - 1) * $pageSize)->limit($pageSize)->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'title' => (string) $row->title,
                'content' => (string) $row->content,
                'biz_type' => (string) $row->biz_type,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

}
