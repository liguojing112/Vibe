<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 超管的资金兜底操作：扣金币（正数）/ 调积分（可正可负）。
 *
 * 必须填原因 —— 这两件事都是直接动人账上的钱，事后要说得清为什么。
 */
class ClAdjustRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'user_id' => 'required|integer|min:1',
            'amount' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => '必须选用户',
            'amount.required' => '必须填数量',
            'amount.not_in' => '数量不能是 0',
            'reason.required' => '必须填原因',
            'reason.max' => '原因最长 200 个字',
        ];
    }
}
