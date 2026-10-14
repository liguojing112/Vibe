<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 保存一组运营配置：`values` 是 `key => 新值` 的映射。
 *
 * 这里只校验「形状」，**每个 key 值合不合法由 `AdminConfigService` 管** ——
 * 汇率不能为 0、分成要在 0~10000 这些规则跟着 key 走，写进 rules 会散得到处都是。
 */
class ClConfigSaveRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'values' => 'required|array|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'values.required' => '没有要保存的配置',
        ];
    }
}
