<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 运营配置里那几张目录表的保存（礼物 / 会员套餐 / 任务 / 标签 / 城市）。
 *
 * 五张表的字段放在一个请求里，**每个页面只传自己那几个** ——
 * 各写一个请求类的话就是五份几乎一样的文件，而真正的校验（任务类型、天数、门槛）
 * 在 `AdminCatalogService` 里，那里能读到别的字段来判断。
 */
class ClCatalogSaveRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'id' => 'nullable|integer|min:1',
            'name' => 'nullable|string|max:64',
            'icon' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'price_coins' => 'nullable|integer|min:0',
            'days' => 'nullable|integer|min:1',
            'type' => 'nullable|string|max:32',
            'target' => 'nullable|integer|min:0',
            'reward' => 'nullable|integer|min:0',
            'gender' => 'nullable|integer|in:1,2',
            'period' => 'nullable|integer|in:1,2',
            'sort' => 'nullable|integer|min:0|max:9999',
            'enabled' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => '名称最长 64 个字',
            'price_coins.min' => '价格不能是负数',
            'days.min' => '天数必须大于 0',
            'sort.max' => '排序值太大了',
        ];
    }
}
