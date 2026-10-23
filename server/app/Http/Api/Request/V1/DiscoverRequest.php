<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 发现页筛选（docs/05 第 4.3 节、docs/01 P07）。
 *
 * `video` / `voice` 是「在线可接听」那两个 Tab（docs/01 第 4 节，P01/P02/P16）：
 * 开关关着时客户端不会传，但服务端一直认 —— 页面开关不该改接口的校验规则。
 */
class DiscoverRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'tab' => 'sometimes|string|in:recommend,new,city,video,voice',
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

    /**
     * 客户端只用 @Query 传 List，Retrofit 展开规则是「一个元素给一个 key」：
     * 勾两个 → `purpose=a&purpose=b`（PHP 得到数组）；只勾一个 → `purpose=a`（PHP 得到字符串）。
     * 不归一化的话，恰好只选了一个交友期待的用户会整页 422。
     */
    protected function validationData(): array
    {
        $data = parent::validationData();

        if (isset($data['purpose']) && is_string($data['purpose'])) {
            $data['purpose'] = [$data['purpose']];
        }

        return $data;
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
