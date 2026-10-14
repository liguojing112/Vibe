<?php

declare(strict_types=1);

namespace App\Repository\Cl\Admin;

use App\Model\Cl\BatchAccount;
use App\Repository\IRepository;

/**
 * 批量开号批次（docs/04 第 7 节）。
 *
 * @extends IRepository<BatchAccount>
 */
final class BatchAccountRepository extends IRepository
{
    public function __construct(
        protected readonly BatchAccount $model
    ) {}

    public function createBatch(array $data): BatchAccount
    {
        /** @var BatchAccount $batch */
        $batch = $this->model->newQuery()->create($data);

        return $batch;
    }

    public function findByBatchNo(string $batchNo): ?BatchAccount
    {
        return $this->model->newQuery()->where('batch_no', $batchNo)->first();
    }

    /**
     * 批次列表，按时间倒序。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pageList(int $page, int $pageSize): array
    {
        $total = $this->model->newQuery()->count();
        $rows = $this->model->newQuery()
            ->orderByDesc('id')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'batch_no' => (string) $row->batch_no,
                'gender' => (int) $row->gender,
                'count' => (int) $row->count,
                'account_prefix' => (string) $row->account_prefix,
                'operator_id' => $row->operator_id === null ? null : (int) $row->operator_id,
                'remark' => (string) ($row->remark ?? ''),
                'exported' => (int) $row->exported === 1,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    /** 标记「这批明文已经导出过了」（批次列表里的「是否已导出」）。 */
    public function markExported(int $id): void
    {
        $this->model->newQuery()->whereKey($id)->update(['exported' => 1]);
    }
}
