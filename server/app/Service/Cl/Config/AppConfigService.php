<?php

declare(strict_types=1);

namespace App\Service\Cl\Config;

use App\Model\Cl\Config;
use App\Repository\Cl\ConfigRepository;
use Hyperf\Redis\Redis;

/**
 * 全项目唯一读配置入口（见 docs/02 第 2.4 节、docs/04 第 6 节）。
 *
 * - 全部配置在 Redis 里缓存一份（key => {value, group}），后台改配置后调 set() 或 forget() 清缓存。
 * - 读出来的值按 cl_config.value_type 转好类型（int / bool / json / string），调用方不用再转。
 * - set() 只能改种子里已定义的 key，不允许新增（铁律：不准自创需求）。
 */
class AppConfigService
{
    public const CACHE_KEY = 'cl:config';

    public function __construct(
        protected ConfigRepository $repository,
        protected Redis $redis,
    ) {}

    /**
     * 读一个配置项。找不到时返回 $default。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key]['value'] ?? $default;
    }

    /**
     * 读一组配置（后台「运营配置」按 group 展示用）。
     *
     * @return array<string, mixed> key => value
     */
    public function getGroup(string $group): array
    {
        $result = [];
        foreach ($this->all() as $key => $item) {
            if ($item['group'] === $group) {
                $result[$key] = $item['value'];
            }
        }

        return $result;
    }

    /**
     * 改一个配置项。key 不存在时返回 false（不新增）。
     */
    public function set(string $key, mixed $value): bool
    {
        $config = $this->repository->findByKey($key);
        if ($config === null) {
            return false;
        }
        $config->value = $this->stringify($value, $config->value_type);
        $config->save();
        $this->forget();

        return true;
    }

    /**
     * 清配置缓存（后台批量改配置后调用）。
     */
    public function forget(): void
    {
        $this->redis->del(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{value: mixed, group: string}>
     */
    private function all(): array
    {
        $cached = $this->redis->get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $map = [];
        /** @var Config $row */
        foreach ($this->repository->list() as $row) {
            $map[$row->key] = [
                'value' => $this->cast($row->value, $row->value_type),
                'group' => $row->group,
            ];
        }
        $this->redis->set(self::CACHE_KEY, json_encode($map, JSON_UNESCAPED_UNICODE));

        return $map;
    }

    private function cast(?string $raw, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $raw,
            'bool' => filter_var($raw ?? '0', \FILTER_VALIDATE_BOOLEAN),
            'json' => $this->decodeJson($raw),
            default => (string) $raw,
        };
    }

    /**
     * JSON 配置解码。
     *
     * 兼容历史上被双重编码的数据（`"[50,100]"`）：解出来还是字符串就再解一层。
     * 迁移 `2026_10_26_000025` 会把存量数据修好，这里再兜一层底，
     * 避免迁移没跑时 App 读到字符串。
     */
    private function decodeJson(?string $raw): mixed
    {
        $decoded = json_decode($raw ?? 'null', true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (is_string($decoded)) {
            $again = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $again;
            }
        }

        return $decoded;
    }

    private function stringify(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => filter_var($value, \FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            'json' => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
