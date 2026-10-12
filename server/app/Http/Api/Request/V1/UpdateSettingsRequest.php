<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 展示开关（docs/05 第 4.2 节）：`show_in_nearby`、`show_in_recommend`，直接生效、不进审核。
 */
class UpdateSettingsRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'show_in_nearby' => 'sometimes|integer|in:0,1',
            'show_in_recommend' => 'sometimes|integer|in:0,1',
        ];
    }

    public function attributes(): array
    {
        return [
            'show_in_nearby' => '展示给附近的人',
            'show_in_recommend' => '在推荐里展示',
        ];
    }
}
