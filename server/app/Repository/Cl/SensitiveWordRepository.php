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
}
