<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 封禁 / 解封（docs/07 第 3.2 节、docs/01 P15）。
 *
 * 两个动作**共用同一个请求类**：解封只需要 `reason`，多传的 `days` 会被忽略 ——
 * 封禁那里 `days = 0` 表示永久（和 `cl_user.ban_until` 为 null 一个口径）。
 */
class ClUserBanRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:200',
            'days' => 'nullable|integer|min:0|max:3650',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '必须填写原因',
            'reason.max' => '原因最长 200 个字',
            'days.integer' => '封禁天数要是整数',
            'days.min' => '封禁天数不能是负数',
            'days.max' => '封禁天数太长了',
        ];
    }
}
