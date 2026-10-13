<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 金币收支明细的查询参数（docs/05 第 4.7 节）。
 *
 * `type` 只认 all / recharge / spend（P24 的三个 Tab），别的值直接 422 ——
 * 与其猜用户想过滤什么，不如让写错的客户端立刻暴露出来。
 */
class CoinLogsRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'type' => 'sometimes|string|in:all,recharge,spend',
            'page' => 'sometimes|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => '明细类型',
            'page' => '页码',
        ];
    }
}
