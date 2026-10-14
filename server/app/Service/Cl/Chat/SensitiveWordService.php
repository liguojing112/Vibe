<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat;

use App\Repository\Cl\SensitiveWordRepository;
use Hyperf\Redis\Redis;

/**
 * 敏感词（docs/04 第 3 节）：**命中只标记不拦截** —— 消息照常送达，
 * 但会在消息上打 `sensitive_hit`，进后台待审核。
 *
 * 词库缓存在 Redis（后台改完调一次 [flush]），避免每条消息都查库。
 */
final class SensitiveWordService
{
    private const CACHE_KEY = 'cl:sensitive_words';

    /** 缓存 10 分钟；后台改词库会主动 flush，这个 TTL 只是兜底。 */
    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly Redis $redis,
        private readonly SensitiveWordRepository $repository,
    ) {}

    public function hit(string $content): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        foreach ($this->words() as $word) {
            if (mb_strpos($content, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * 命中了哪几个词（后台待审消息要**把命中的词标红**，docs/07 第 3.7 节）。
     *
     * 消息表上只存了「有没有命中」，没存命中的是哪几个 —— 那是会跟着词库变的，
     * 存下来的话删了个词它还在。所以这里用**当前**词库现算。
     *
     * @return array<int,string>
     */
    public function matches(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $hits = [];
        foreach ($this->words() as $word) {
            if (mb_strpos($content, $word) !== false) {
                $hits[] = $word;
            }
        }

        return $hits;
    }

    /** 后台改词库后调一次（测试里也用它清缓存）。 */
    public function flush(): void
    {
        $this->redis->del(self::CACHE_KEY);
    }

    /**
     * @return array<int,string>
     */
    private function words(): array
    {
        $cached = $this->redis->get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_string'));
            }
        }

        $words = $this->repository->enabledWords();
        $this->redis->setex(self::CACHE_KEY, self::CACHE_TTL_SECONDS, (string) json_encode($words, JSON_UNESCAPED_UNICODE));

        return $words;
    }
}
