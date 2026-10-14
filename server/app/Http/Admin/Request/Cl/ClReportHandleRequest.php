<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 处理举报：判定违规 / 不违规，可顺手封号（docs/07 第 3.9 节）。 */
class ClReportHandleRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'violation' => 'required|boolean',
            'remark' => 'nullable|string|max:200',
            'ban_days' => 'nullable|integer|min:0|max:3650',
            'ban_reason' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'violation.required' => '必须选判定结果',
            'remark.max' => '处理说明最长 200 个字',
            'ban_reason.max' => '封禁原因最长 200 个字',
        ];
    }
}
