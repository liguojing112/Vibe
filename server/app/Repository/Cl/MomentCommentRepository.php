<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\MomentComment;
use App\Repository\IRepository;

/**
 * @extends IRepository<MomentComment>
 */
final class MomentCommentRepository extends IRepository
{
    public function __construct(
        protected readonly MomentComment $model
    ) {}

    /**
     * 某条动态的评论，按时间正序（老的在上，和聊天一致）。
     *
     * @return array<int,MomentComment>
     */
    public function pageByMoment(int $momentId, int $offset, int $limit): array
    {
        /** @var array<int,MomentComment> $rows */
        $rows = $this->visible($this->model->newQuery()->where('moment_id', $momentId))
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return $rows;
    }

    public function countByMoment(int $momentId): int
    {
        return $this->visible($this->model->newQuery()->where('moment_id', $momentId))->count();
    }

    public function createComment(int $momentId, int $userId, string $content): MomentComment
    {
        return $this->model->newQuery()->create([
            'moment_id' => $momentId,
            'user_id' => $userId,
            'content' => $content,
            'status' => MomentComment::STATUS_NORMAL,
        ]);
    }

    public function findVisible(int $id): ?MomentComment
    {
        /** @var null|MomentComment $row */
        $row = $this->visible($this->model->newQuery()->whereKey($id))->first();

        return $row;
    }

    public function softDelete(int $id): void
    {
        $this->model->newQuery()->whereKey($id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    private function visible($query)
    {
        return $query->whereNull('deleted_at')->where('status', MomentComment::STATUS_NORMAL);
    }
}
