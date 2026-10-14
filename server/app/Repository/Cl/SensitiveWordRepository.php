<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\SensitiveWord;
use App\Repository\IRepository;

/**
 * @extends IRepository<SensitiveWord>
 */
final class SensitiveWordRepository extends IRepository
{
    public function __construct(
        protected readonly SensitiveWord $model
    ) {}

    /**
     * 启用中的敏感词。
     *
     * @return array<int,string>
     */
    public function enabledWords(): array
    {
        $words = $this->model->newQuery()
            ->where('enabled', 1)
            ->orderBy('id')
            ->pluck('word')
            ->all();

        return array_values(array_map(static fn (mixed $word): string => (string) $word, $words));
    }

    /**
     * 后台列表（docs/07 第 3.8 节），支持按词模糊搜。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function pageList(?string $keyword, int $page, int $pageSize): array
    {
        $query = $this->model->newQuery();
        if ($keyword !== null && trim($keyword) !== '') {
            $query->where('word', 'like', '%' . trim($keyword) . '%');
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('id')->offset(($page - 1) * $pageSize)->limit($pageSize)->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'id' => (int) $row->id,
                'word' => (string) $row->word,
                'enabled' => (int) $row->enabled === 1,
                'created_at' => $row->created_at?->toDateTimeString(),
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    public function findByWord(string $word): ?SensitiveWord
    {
        /** @var null|SensitiveWord $row */
        $row = $this->model->newQuery()->where('word', $word)->first();

        return $row;
    }

    public function createWord(string $word, int $creatorId): SensitiveWord
    {
        /** @var SensitiveWord $row */
        $row = $this->model->newQuery()->create([
            'word' => $word,
            'enabled' => 1,
            'creator_id' => $creatorId,
        ]);

        return $row;
    }

    public function updateWord(int $id, string $word, bool $enabled): void
    {
        $this->model->newQuery()->whereKey($id)->update(['word' => $word, 'enabled' => $enabled ? 1 : 0]);
    }

    /**
     * @param array<int,int> $ids
     * @return int 删掉几条
     */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $this->model->newQuery()->whereIn('id', $ids)->delete();
    }
}
