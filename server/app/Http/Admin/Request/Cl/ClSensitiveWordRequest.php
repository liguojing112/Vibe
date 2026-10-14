<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 新增/修改敏感词（docs/07 第 3.8 节）。 */
class ClSensitiveWordRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'word' => 'required|string|max:64',
            'enabled' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'word.required' => '敏感词不能为空',
            'word.max' => '敏感词最长 64 个字',
        ];
    }
}
