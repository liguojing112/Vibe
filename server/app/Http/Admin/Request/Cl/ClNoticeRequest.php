<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 发通知（docs/07 第 3.20 节）：全员公告不传 `user_ids`，定向发送才传。 */
class ClNoticeRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:64',
            'content' => 'nullable|string|max:500',
            'user_ids' => 'nullable|array|max:200',
            'user_ids.*' => 'integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => '必须填标题',
            'title.max' => '标题最长 64 个字',
            'content.max' => '正文最长 500 个字',
            'user_ids.max' => '一次最多发给 200 个用户',
        ];
    }
}
