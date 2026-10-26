<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 用户列表里的「加款」快捷操作。
 *
 * 男号加金币（走后台充值单），女号加积分（走积分补发）；
 * 数量、备注必填，实收金额只对男号有意义（0 = 赠送）。
 */
class ClGrantRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|min:1',
            'amount' => 'required|integer|min:1|max:10000000',
            'amount_fen' => 'nullable|integer|min:0',
            'remark' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '必须选用户',
            'amount.required' => '必须填加款数量',
            'amount.min' => '加款数量必须是正整数',
            'amount_fen.min' => '实收金额不能是负数',
            'remark.required' => '加款必须填备注',
            'remark.max' => '备注最长 200 个字',
        ];
    }
}
