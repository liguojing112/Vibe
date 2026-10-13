<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\RealName;
use App\Repository\IRepository;

/**
 * @extends IRepository<RealName>
 */
final class RealNameRepository extends IRepository
{
    public function __construct(
        protected readonly RealName $model
    ) {}

    public function findByUserId(int $userId): ?RealName
    {
        /** @var null|RealName $row */
        $row = $this->model->newQuery()->where('user_id', $userId)->first();

        return $row;
    }

    /**
     * 这个身份证号被哪些别的号用过（**只给后台提示，不拦截**）。
     *
     * @return array<int,int> 用户 id 列表
     */
    public function userIdsByHash(string $hash, int $exceptUserId = 0): array
    {
        return $this->model->newQuery()
            ->where('id_card_hash', $hash)
            ->where('user_id', '<>', $exceptUserId)
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
