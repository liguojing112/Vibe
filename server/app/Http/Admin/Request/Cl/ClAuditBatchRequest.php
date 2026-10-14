<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 批量审核（勾选多条一次通过/驳回，docs/07 第 3.4 节）。 */
class ClAuditBatchRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer|min:1',
            'action' => 'required|string|in:approve,reject',
            // 驳回才要原因，通过不传
            'reason' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => '没有选中任何记录',
            'ids.max' => '一次最多处理 200 条',
            'action.in' => '动作只能是通过或驳回',
            'reason.max' => '原因最长 200 个字',
        ];
    }
}
