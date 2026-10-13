<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 通知列表的查询参数（docs/05 第 4.9 节 `/notice/list`）。
 *
 * `category` 必填（1 系统通知 / 2 互动通知）—— P10 就是两个 Tab，没有「全部」这一说。
 */
class NoticeListRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'category' => 'required|integer|in:1,2',
            'page' => 'sometimes|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'category' => '通知分类',
            'page' => '页码',
        ];
    }
}
