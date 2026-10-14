<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 后台给男用户开通/延长会员（docs/07 第 3.2 节）。
 *
 * `plan_id` 和 `days` 二选一：选了套餐就用套餐的天数，没选就按填的天数开。
 */
class ClUserVipGrantRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'plan_id' => 'nullable|integer|min:1',
            'days' => 'nullable|integer|min:1|max:3650',
            'remark' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'days.min' => '开通天数必须大于 0',
            'days.max' => '开通天数太长了',
            'remark.max' => '备注最长 200 个字',
        ];
    }
}
