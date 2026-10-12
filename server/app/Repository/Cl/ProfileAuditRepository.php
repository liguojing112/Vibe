<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\ProfileAudit;
use App\Repository\IRepository;
use Hyperf\Collection\Collection;

/**
 * @extends IRepository<ProfileAudit>
 */
final class ProfileAuditRepository extends IRepository
{
    public function __construct(
        protected readonly ProfileAudit $model
    ) {}

    public function findPending(int $userId, string $field): ?ProfileAudit
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('field', $field)
            ->where('status', ProfileAudit::STATUS_PENDING)
            ->first();
    }

    /**
     * 某个用户所有待审记录（`/user/me` 用它拼「审核中」角标）。
     *
     * @return Collection<int,ProfileAudit>
     */
    public function listPending(int $userId): Collection
    {
        /** @var Collection<int,ProfileAudit> $rows */
        $rows = $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('status', ProfileAudit::STATUS_PENDING)
            ->get();

        return $rows;
    }

    /**
     * 同一用户同一字段只保留一条待审记录：有就覆盖，没有就新建（docs/04 第 1 节）。
     */
    public function upsertPending(int $userId, string $field, ?string $oldValue, string $newValue): ProfileAudit
    {
        $pending = $this->findPending($userId, $field);
        if ($pending instanceof ProfileAudit) {
            $pending->update([
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]);
            return $pending;
        }

        return $this->model->newQuery()->create([
            'user_id' => $userId,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'status' => ProfileAudit::STATUS_PENDING,
        ]);
    }

    /**
     * 提交的内容和待审记录一致（用户没真改），把待审记录删掉。
     */
    public function deletePending(int $userId, string $field): void
    {
        $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('field', $field)
            ->where('status', ProfileAudit::STATUS_PENDING)
            ->delete();
    }
}
