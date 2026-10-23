<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\Moment;
use App\Model\Cl\MomentComment;
use App\Repository\IRepository;
use Hyperf\DbConnection\Db;

/**
 * @extends IRepository<Moment>
 */
final class MomentRepository extends IRepository
{
    public function __construct(
        protected readonly Moment $model
    ) {}

    /**
     * 某个用户的动态（正常状态、未删除），按时间倒序。
     *
     * @return array<int,Moment>
     */
    public function pageByUser(int $userId, int $offset, int $limit): array
    {
        /** @var array<int,Moment> $rows */
        $rows = $this->visible($this->model->newQuery()->where('user_id', $userId))
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    public function countByUser(int $userId): int
    {
        return $this->visible($this->model->newQuery()->where('user_id', $userId))->count();
    }

    /**
     * 最近几条（个人主页的「朋友圈」区块用）。
     *
     * @return array<int,Moment>
     */
    public function latestByUser(int $userId, int $limit): array
    {
        /** @var array<int,Moment> $rows */
        $rows = $this->visible($this->model->newQuery()->where('user_id', $userId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    public function findVisible(int $id): ?Moment
    {
        /** @var null|Moment $row */
        $row = $this->visible($this->model->newQuery()->whereKey($id))->first();

        return $row;
    }

    /**
     * @param array<int,string> $images
     */
    public function createMoment(int $userId, string $content, array $images): Moment
    {
        return $this->model->newQuery()->create([
            'user_id' => $userId,
            'content' => $content,
            'images' => $images,
            'like_count' => 0,
            'comment_count' => 0,
            'status' => Moment::STATUS_NORMAL,
        ]);
    }

    public function softDelete(int $id): void
    {
        $this->model->newQuery()->whereKey($id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /** 点赞数 +1（只由 [addIfNew] 真的插入了新行时调用）。 */
    public function incrementLike(int $id): int
    {
        $this->model->newQuery()->whereKey($id)->increment('like_count');

        return (int) $this->model->newQuery()->whereKey($id)->value('like_count');
    }

    /** 点赞数 -1，兜底不让它变成负数。 */
    public function decrementLike(int $id): int
    {
        $this->model->newQuery()->whereKey($id)->update([
            'like_count' => Db::raw('GREATEST(like_count - 1, 0)'),
        ]);

        return (int) $this->model->newQuery()->whereKey($id)->value('like_count');
    }

    public function incrementComment(int $id): void
    {
        $this->model->newQuery()->whereKey($id)->increment('comment_count');
    }

    public function decrementComment(int $id): void
    {
        $this->model->newQuery()->whereKey($id)->update([
            'comment_count' => Db::raw('GREATEST(comment_count - 1, 0)'),
        ]);
    }

    /**
     * 后台列表：可按用户 / 状态过滤，含已隐藏的。
     *
     * @param array<string,mixed> $params
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pageForAdmin(array $params, int $offset, int $limit): array
    {
        $query = $this->model->newQuery()->whereNull('deleted_at');
        if (! empty($params['user_id'])) {
            $query->where('user_id', (int) $params['user_id']);
        }
        if (! empty($params['status'])) {
            $query->where('status', (int) $params['status']);
        }

        $total = (clone $query)->count();
        $list = $query->orderByDesc('id')->offset($offset)->limit($limit)->get()->toArray();

        return ['list' => $list, 'total' => $total];
    }

    /** 对账：按真实行数回写计数。 */
    public function recount(int $id): void
    {
        $likes = Db::table('cl_moment_like')->where('moment_id', $id)->count();
        $comments = Db::table('cl_moment_comment')
            ->where('moment_id', $id)
            ->whereNull('deleted_at')
            ->where('status', MomentComment::STATUS_NORMAL)
            ->count();

        $this->model->newQuery()->whereKey($id)->update([
            'like_count' => $likes,
            'comment_count' => $comments,
        ]);
    }

    private function visible($query)
    {
        return $query->whereNull('deleted_at')->where('status', Moment::STATUS_NORMAL);
    }
}
