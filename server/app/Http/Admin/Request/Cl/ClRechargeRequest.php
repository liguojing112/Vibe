<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 后台充值（docs/07 第 3.10 节）：只能给男用户充，金币数必须为正。 */
class ClRechargeRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|min:1',
            'coins' => 'required|integer|min:1|max:10000000',
            // 实收金额（分）：0 = 赠送
            'amount_fen' => 'nullable|integer|min:0',
            'pay_channel' => 'required|string|in:WECHAT,ALIPAY,OTHER,GIFT',
            'remark' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '必须选用户',
            'coins.required' => '必须填充值金币数',
            'coins.min' => '充值金币数必须是正整数',
            'amount_fen.min' => '实收金额不能是负数',
            'pay_channel.in' => '支付方式不正确',
            'remark.max' => '备注最长 200 个字',
        ];
    }
}
