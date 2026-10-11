<?php

declare(strict_types=1);

namespace App\Http\Api;

use App\Model\Enums\Cl\BizCode;
use Hyperf\Collection\Collection;
use Hyperf\Contract\Arrayable;

/**
 * App 接口统一响应（docs/05 第 1 节）。
 * JSON 结构与 MineAdmin 的 App\Http\Common\Result 完全一致，区别是 code 允许是业务码整数。
 */
class ApiResult implements Arrayable
{
    public function __construct(
        public int $code = 200,
        public ?string $message = '成功',
        public mixed $data = [],
    ) {}

    public static function success(mixed $data = [], string $message = '成功'): self
    {
        return new self(200, $message, $data);
    }

    /**
     * 业务错误。没有额外数据时 data 是 null（不是空数组）：
     * 客户端按具体 DTO 解析 data，空数组会解析失败。
     */
    public static function error(BizCode $code, ?string $message = null, mixed $data = null): self
    {
        return new self($code->value, $message ?? $code->message(), $data);
    }

    /**
     * 分页响应：{list, total, page, page_size, has_more}。
     *
     * @param Collection|array $list
     */
    public static function page(mixed $list, int $total, int $page, int $pageSize): self
    {
        return new self(200, '成功', [
            'list' => $list instanceof Collection ? $list->toArray() : $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'has_more' => $page * $pageSize < $total,
        ]);
    }

    /**
     * 游标分页响应（聊天消息用）：{list, has_more}。
     *
     * @param Collection|array $list
     */
    public static function cursor(mixed $list, bool $hasMore): self
    {
        return new self(200, '成功', [
            'list' => $list instanceof Collection ? $list->toArray() : $list,
            'has_more' => $hasMore,
        ]);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
