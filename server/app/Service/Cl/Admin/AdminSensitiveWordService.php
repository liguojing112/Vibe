<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\SensitiveWord;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\SensitiveWordRepository;
use App\Service\Cl\Chat\SensitiveWordService;

/**
 * 后台敏感词库（docs/07 第 3.8 节）：增删改、启停、批量导入。
 *
 * **每次改完都要清一次服务端缓存** —— 敏感词是缓存在内存里的（`SensitiveWordService`），
 * 不清的话「后台加了个词，App 里发出去还是能发」，会以为改了个假的。
 */
final class AdminSensitiveWordService
{
    /** 一个词最长多少（和 `cl_sensitive_word.word` 的列宽一致）。 */
    private const MAX_LENGTH = 64;

    /** 一次最多导入多少个词。 */
    private const MAX_IMPORT = 2000;

    public function __construct(
        private readonly SensitiveWordRepository $repository,
        private readonly SensitiveWordService $sensitiveWordService,
    ) {}

    /**
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function list(?string $keyword, int $page, int $pageSize): array
    {
        return $this->repository->pageList($keyword, $page, $pageSize);
    }

    public function create(AdminUser $operator, string $word): void
    {
        $word = $this->normalize($word);
        if ($this->repository->findByWord($word) !== null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这个词已经在库里了');
        }

        $this->repository->createWord($word, $operator->id);
        $this->flush();
    }

    public function update(AdminUser $operator, int $id, string $word, bool $enabled): void
    {
        $word = $this->normalize($word);
        $exists = $this->repository->findByWord($word);
        if ($exists !== null && (int) $exists->id !== $id) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这个词已经在库里了');
        }

        $this->repository->updateWord($id, $word, $enabled);
        $this->flush();
    }

    /**
     * 删除。
     *
     * @param array<int,int> $ids
     * @return int 删掉几条
     */
    public function remove(AdminUser $operator, array $ids): int
    {
        $deleted = $this->repository->deleteByIds($ids);
        $this->flush();

        return $deleted;
    }

    /**
     * 批量导入：**一行一个词**（docs/07 第 3.8 节）。
     *
     * 已经有的、空行、太长的都跳过并数出来 —— 导 500 个词的时候，
     * 有一个重复就整批失败是最难受的，不如「导进去 N 个、跳过 M 个」说清楚。
     *
     * @return array{added: int, skipped: array<int,string>}
     */
    public function import(AdminUser $operator, string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $added = 0;
        $skipped = [];

        foreach ($lines as $line) {
            $word = trim($line);
            if ($word === '') {
                continue;
            }
            if ($added >= self::MAX_IMPORT) {
                $skipped[] = '超出单次上限 ' . self::MAX_IMPORT . ' 个，剩下的没导入';
                break;
            }
            if (mb_strlen($word) > self::MAX_LENGTH || $this->repository->findByWord($word) !== null) {
                $skipped[] = $word;
                continue;
            }

            $this->repository->createWord($word, $operator->id);
            ++$added;
        }

        $this->flush();

        return ['added' => $added, 'skipped' => $skipped];
    }

    // ---------- 内部 ----------

    private function normalize(string $word): string
    {
        $word = trim($word);
        if ($word === '') {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '敏感词不能为空');
        }
        if (mb_strlen($word) > self::MAX_LENGTH) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '敏感词最长 ' . self::MAX_LENGTH . ' 个字');
        }

        return $word;
    }

    /** 改完词库必须清缓存，不然新词不生效（老词也还在）。 */
    private function flush(): void
    {
        $this->sensitiveWordService->flush();
    }
}
