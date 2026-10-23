<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use App\Model\Cl\MomentComment;
use Hyperf\Validation\Request\FormRequest;

/** 发评论：1~500 字。 */
class CreateMomentCommentRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:' . MomentComment::MAX_CONTENT,
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => '评论内容',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => '评论不能为空',
            'content.max' => '评论最多 ' . MomentComment::MAX_CONTENT . ' 个字',
        ];
    }
}
