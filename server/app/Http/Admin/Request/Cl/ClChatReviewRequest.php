<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 标记消息（正常 / 违规），违规时可以顺手封了发送方（docs/07 第 3.7 节）。 */
class ClChatReviewRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer|min:1',
            // 2 = 标记正常，3 = 标记违规（和 `cl_message.review_status` 一致）
            'status' => 'required|integer|in:2,3',
            'ban_days' => 'nullable|integer|min:0|max:3650',
            'ban_reason' => 'nullable|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => '没有选中任何消息',
            'ids.max' => '一次最多处理 200 条',
            'status.in' => '只能标记为正常或违规',
            'ban_reason.max' => '封禁原因最长 200 个字',
        ];
    }
}
