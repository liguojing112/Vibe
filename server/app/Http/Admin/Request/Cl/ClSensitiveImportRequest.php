<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 批量导入敏感词：一行一个（docs/07 第 3.8 节）。 */
class ClSensitiveImportRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'text' => 'required|string|max:200000',
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => '没有可导入的内容',
        ];
    }
}
