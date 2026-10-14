<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 后台调整女用户等级（docs/07 第 3.2 节、docs/06 第 5 节）。
 */
class ClUserLevelRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'level' => 'required|integer|between:1,5',
            'locked' => 'nullable|boolean',
            'reason' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'level.required' => '必须选等级',
            'level.between' => '等级只能是 1~5',
            'reason.required' => '调整等级必须填原因',
            'reason.max' => '原因最长 200 个字',
        ];
    }
}
