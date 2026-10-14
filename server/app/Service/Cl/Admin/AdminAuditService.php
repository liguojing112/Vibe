<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminAuditRepository;
use App\Repository\Cl\UserPhotoRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Notice\NoticeService;
use App\Service\Cl\RealName\RealNameService;

/**
 * 后台审核中心（docs/07 第 3.4、3.5 节）：资料审核 + 实名审核。
 *
 * 三条规矩：
 * 1. **通过才动 `cl_user`**：审核期间用户看到的还是旧资料，通过那一刻才换新的（docs/01 P14）；
 * 2. **相册那一条是个特例**：`old_value`/`new_value` 存的是 `cl_user_photo.id` 列表，
 *    通过 = 新照片置为已通过 + 旧照片删掉，驳回 = 把待审的新照片丢掉（旧相册不动）；
 * 3. **身份证明文只有看一眼的份**：列表里永远是打码的，点「查看」才解密，
 *    而且**每次查看都要写一条后台动作流水**（这是能定位到真人的信息，事后必须说得清谁看过）。
 */
final class AdminAuditService
{
    use RetriesTransactions;

    /** 字段 → 给用户看的名字（通知文案和列表都用它）。 */
    private const FIELD_LABELS = [
        ProfileAudit::FIELD_AVATAR => '头像',
        ProfileAudit::FIELD_NICKNAME => '昵称',
        ProfileAudit::FIELD_BIO => '简介',
        ProfileAudit::FIELD_PHOTO => '相册',
    ];

    public function __construct(
        private readonly AdminAuditRepository $repository,
        private readonly UserRepository $userRepository,
        private readonly UserPhotoRepository $photoRepository,
        private readonly RealNameService $realNameService,
        private readonly NoticeService $noticeService,
    ) {}

    // ---------- 资料审核 ----------

    /**
     * 审核列表。相册那条的 `old_value`/`new_value` 是 id 列表，这里换成图给前端预览。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function profileList(int $status, int $page, int $pageSize): array
    {
        $result = $this->repository->profilePage($status, $page, $pageSize);

        foreach ($result['list'] as &$row) {
            $row['field_label'] = self::FIELD_LABELS[$row['field']] ?? (string) $row['field'];
            if ($row['field'] === ProfileAudit::FIELD_PHOTO) {
                $row['old_photos'] = $this->photoRepository->listByIds($this->parseIds($row['old_value']));
                $row['new_photos'] = $this->photoRepository->listByIds($this->parseIds($row['new_value']));
            }
        }
        unset($row);

        return $result;
    }

    public function approveProfile(AdminUser $operator, int $auditId): void
    {
        $audit = $this->requirePending($auditId);
        /** @var User $user */
        $user = $this->userRepository->findById($audit->user_id);

        $this->runTransaction(function () use ($operator, $audit, $user): void {
            if ($audit->field === ProfileAudit::FIELD_PHOTO) {
                $this->photoRepository->approveByIds($this->parseIds($audit->new_value));
                $this->photoRepository->deleteByIds($this->parseIds($audit->old_value));
            } else {
                $this->userRepository->saveById($user->id, [$audit->field => (string) $audit->new_value]);
            }

            $this->repository->markProfileAudited(
                (int) $audit->id,
                ProfileAudit::STATUS_APPROVED,
                null,
                $operator->id,
            );

            $this->noticeService->profileReviewed(
                (int) $user->id,
                true,
                self::FIELD_LABELS[$audit->field] ?? (string) $audit->field,
            );
        });
    }

    /** 驳回只通知，不动 `cl_user`；相册那批待审的新照片直接丢掉（旧相册保持原样）。 */
    public function rejectProfile(AdminUser $operator, int $auditId, string $reason): void
    {
        $audit = $this->requirePending($auditId);
        $reason = trim($reason);
        if ($reason === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '驳回必须填写原因');
        }

        $this->runTransaction(function () use ($operator, $audit, $reason): void {
            if ($audit->field === ProfileAudit::FIELD_PHOTO) {
                $this->photoRepository->deleteByIds($this->parseIds($audit->new_value));
            }

            $this->repository->markProfileAudited(
                (int) $audit->id,
                ProfileAudit::STATUS_REJECTED,
                $reason,
                $operator->id,
            );

            $this->noticeService->profileReviewed(
                (int) $audit->user_id,
                false,
                self::FIELD_LABELS[$audit->field] ?? (string) $audit->field,
                $reason,
            );
        });
    }

    /**
     * 批量通过 / 驳回（docs/07 第 3.4 节：支持勾选批量操作）。
     *
     * 一条失败不影响其它条 —— 逐条做，把失败的 id 收回来告诉前端，
     * 而不是整批回滚（审核员勾了 20 条，不该因为其中一条状态变了就全部白做）。
     *
     * @param array<int,int> $ids
     * @return array{success: int, failed: array<int,int>}
     */
    public function batchProfile(AdminUser $operator, array $ids, bool $approve, string $reason): array
    {
        $success = 0;
        $failed = [];
        foreach ($ids as $id) {
            try {
                $approve
                    ? $this->approveProfile($operator, (int) $id)
                    : $this->rejectProfile($operator, (int) $id, $reason);
                ++$success;
            } catch (ClBusinessException) {
                $failed[] = (int) $id;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    // ---------- 实名审核 ----------

    /**
     * 实名列表。身份证号只给打码的，另外带上「同一个身份证还被几个号用过」。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function realNameList(int $status, int $page, int $pageSize): array
    {
        $result = $this->repository->realNamePage($status, $page, $pageSize);

        $counts = $this->repository->hashCounts(array_column($result['list'], 'id_card_hash'));
        foreach ($result['list'] as &$row) {
            // 减掉自己：这个数要表达的是「除了他还有几个号用了同一张身份证」
            $row['duplicate_count'] = max(0, ($counts[(string) $row['id_card_hash']] ?? 1) - 1);
            unset($row['id_card_hash']);
        }
        unset($row);

        return $result;
    }

    public function approveRealName(AdminUser $operator, int $id): void
    {
        $this->realNameService->approve($this->requireUserOfRealName($id), $operator->id);
    }

    public function rejectRealName(AdminUser $operator, int $id, string $reason): void
    {
        $this->realNameService->reject($this->requireUserOfRealName($id), $operator->id, $reason);
    }

    /**
     * 看一眼身份证明文（列表里永远是打码的）。
     *
     * 每次都要留一条流水 —— 这是能直接定位到真人的信息，事后得说得清谁在什么时候看过。
     *
     * @return array{id: int, user_id: int, real_name: string, id_card: string}
     */
    public function plainIdCard(AdminUser $operator, int $id): array
    {
        $row = $this->repository->findRealName($id);
        if ($row === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '实名记录不存在');
        }

        AdminActionLog::query()->create([
            'user_id' => (int) $row->user_id,
            'action' => AdminActionLog::ACTION_PROFILE,
            'detail' => '查看了身份证明文',
            'operator_id' => $operator->id,
            'operator_name' => (string) $operator->username,
        ]);

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'real_name' => (string) $row->real_name,
            'id_card' => $this->realNameService->decrypt((string) $row->id_card_encrypted),
        ];
    }

    // ---------- 内部 ----------

    private function requirePending(int $auditId): ProfileAudit
    {
        $audit = $this->repository->findProfileAudit($auditId);
        if ($audit === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '审核记录不存在');
        }
        if ((int) $audit->status !== ProfileAudit::STATUS_PENDING) {
            // 审核员点了两次、或者另一个审核员已经处理过了
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这条已经审过了');
        }

        return $audit;
    }

    private function requireUserOfRealName(int $id): User
    {
        $row = $this->repository->findRealName($id);
        if ($row === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '实名记录不存在');
        }
        if ((int) $row->status !== RealName::STATUS_AUDITING) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这条已经审过了');
        }

        $user = $this->userRepository->findById($row->user_id);
        if (! $user instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $user;
    }

    /**
     * `"1,2,3"` → `[1,2,3]`（相册审核存的 id 列表）。
     *
     * @return array<int,int>
     */
    private function parseIds(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }
}
