<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 驳回审核（资料 / 实名共用）：原因必填（docs/07 第 3.4、3.5 节）。 */
class ClAuditRejectRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '驳回必须填写原因',
            'reason.max' => '原因最长 200 个字',
        ];
    }
}
