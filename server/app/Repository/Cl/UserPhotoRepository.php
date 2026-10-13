<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\UserPhoto;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<UserPhoto>
 */
final class UserPhotoRepository extends IRepository
{
    public function __construct(
        protected readonly UserPhoto $model
    ) {}

    /**
     * 某个用户的全部相册（含待审），按排序取。
     *
     * @return Collection<int,UserPhoto>
     */
    public function listByUser(int $userId): Collection
    {
        /** @var Collection<int,UserPhoto> $rows */
        $rows = $this->model->newQuery()
            ->where('user_id', $userId)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $rows;
    }

    /**
     * 删掉某个用户指定审核状态的相册行（null = 全删）。
     */
    public function deleteByUser(int $userId, ?int $auditStatus = null): void
    {
        $query = $this->model->newQuery()->where('user_id', $userId);
        if ($auditStatus !== null) {
            $query->where('audit_status', $auditStatus);
        }
        $query->delete();
    }

    /**
     * 写一行相册，返回带 id 的模型（相册审核记录要存 id）。
     */
    public function createRow(int $userId, string $url, int $sort, int $auditStatus): UserPhoto
    {
        return $this->model->newQuery()->create([
            'user_id' => $userId,
            'url' => $url,
            'sort' => $sort,
            'audit_status' => $auditStatus,
        ]);
    }

    /** 审核通过的照片数（「完善照片」任务用，docs/06 第 7 节）。 */
    public function countApproved(int $userId): int
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('audit_status', UserPhoto::AUDIT_APPROVED)
            ->count();
    }
}
