<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use App\Service\Cl\User\ProfileService;
use Hyperf\Validation\Request\FormRequest;

/**
 * 相册整体提交（docs/05 第 4.2 节）：`urls` 是图片地址数组，顺序即展示顺序。
 * 传空数组表示清空相册，所以用 `present` 而不是 `required`。
 */
class UpdatePhotosRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'urls' => 'present|array|max:' . ProfileService::MAX_PHOTOS,
            'urls.*' => 'string|max:255',
        ];
    }

    public function attributes(): array
    {
        return [
            'urls' => '相册',
            'urls.*' => '图片',
        ];
    }

    public function messages(): array
    {
        return [
            'urls.max' => '相册最多 ' . ProfileService::MAX_PHOTOS . ' 张',
        ];
    }
}
