<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 后台把动态设为「正常 / 隐藏」。 */
class ClMomentStatusRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'status' => 'required|integer|in:1,2',
        ];
    }

    public function attributes(): array
    {
        return [
            'status' => '状态',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => '状态只能是 1（正常）或 2（隐藏）',
        ];
    }
}
