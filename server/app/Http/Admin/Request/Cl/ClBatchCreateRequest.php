<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 批量开号（docs/07 第 3.3 节）：性别、数量、账号前缀、备注。
 */
class ClBatchCreateRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'gender' => 'required|integer|in:1,2',
            'count' => 'required|integer|between:1,200',
            'prefix' => 'nullable|string|max:8',
            'remark' => 'nullable|string|max:200',
            'initial_amount' => 'nullable|integer|min:0|max:10000000',
            'assign_avatar' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'gender.required' => '必须选性别',
            'gender.in' => '性别只能是男或女',
            'count.required' => '必须填数量',
            'count.between' => '一次最多开 200 个号',
            'prefix.max' => '账号前缀最长 8 位',
            'remark.max' => '备注最长 200 个字',
            'initial_amount.min' => '初始金币/积分不能是负数',
            'initial_amount.max' => '初始金币/积分太大了',
        ];
    }
}
