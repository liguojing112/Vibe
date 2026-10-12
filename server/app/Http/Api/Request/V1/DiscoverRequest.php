<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 发现页筛选（docs/05 第 4.3 节、docs/01 P07）。
 */
class DiscoverRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'tab' => 'sometimes|string|in:recommend,new,city',
            'keyword' => 'sometimes|nullable|string|max:32',
            'city' => 'sometimes|nullable|string|max:32',
            'age_min' => 'sometimes|nullable|integer|min:18|max:99',
            'age_max' => 'sometimes|nullable|integer|min:18|max:99|gte:age_min',
            'purpose' => 'sometimes|array',
            'purpose.*' => 'string|max:16',
            'verified' => 'sometimes|boolean',
            'complete' => 'sometimes|boolean',
            'online_only' => 'sometimes|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'tab' => '分类',
            'keyword' => '关键词',
            'city' => '城市',
            'age_min' => '最小年龄',
            'age_max' => '最大年龄',
            'purpose' => '交友期待',
            'verified' => '真人认证',
            'complete' => '资料完善',
            'online_only' => '只看在线',
        ];
    }

    public function messages(): array
    {
        return [
            'age_max.gte' => '最大年龄不能小于最小年龄',
        ];
    }
}
