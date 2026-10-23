<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use App\Model\Cl\Moment;
use Hyperf\Validation\Request\FormRequest;

/**
 * 发布动态：文字和图片至少给一个（"两者都空"在 Service 里判，这里只管类型和长度）。
 */
class PublishMomentRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'content' => 'nullable|string|max:' . Moment::MAX_CONTENT,
            'images' => 'nullable|array|max:' . Moment::MAX_IMAGES,
            'images.*' => 'string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => '内容',
            'images' => '图片',
            'images.*' => '图片地址',
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => '内容最多 ' . Moment::MAX_CONTENT . ' 个字',
            'images.max' => '最多 ' . Moment::MAX_IMAGES . ' 张图片',
        ];
    }
}
