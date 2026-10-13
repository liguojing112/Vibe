<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\CoinLog;
use App\Repository\IRepository;

/**
 * @extends IRepository<CoinLog>
 */
final class CoinLogRepository extends IRepository
{
    public function __construct(
        protected readonly CoinLog $model
    ) {}

    /**
     * 同一笔业务是否已经记过账（幂等用，见 04 文档 cl_coin_log 的唯一索引）。
     */
    public function existsByRef(string $type, string $refType, int $refId): bool
    {
        return $this->model->newQuery()
            ->where('type', $type)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->exists();
    }

    /**
     * @param array<string,mixed> $data
     */
    public function add(array $data): void
    {
        $this->model->newQuery()->create($data);
    }

    /**
     * 某用户全部金币流水的 change 之和（对账用，见 docs/06 第 10 节场景 34）。
     */
    /**
     * 某个用户的金币流水（P24 明细列表），按时间倒序。
     *
     * @param array<int,string> $types 空数组 = 不按类型过滤
     * @return array<int,array<string,mixed>>
     */
    public function pageForUser(int $userId, array $types, int $offset, int $limit): array
    {
        $query = $this->model->newQuery()->where('user_id', $userId);
        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        $rows = $query->orderByDesc('id')->offset($offset)->limit($limit)->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'title' => (string) $row->title,
                'change' => (int) $row->change,
                'balance_after' => (int) $row->balance_after,
                'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
            ];
        }

        return $list;
    }

    /**
     * @param array<int,string> $types
     */
    public function countForUser(int $userId, array $types): int
    {
        $query = $this->model->newQuery()->where('user_id', $userId);
        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        return $query->count();
    }

    public function sumChangeByUser(int $userId): int
    {
        return (int) $this->model->newQuery()->where('user_id', $userId)->sum('change');
    }
}
