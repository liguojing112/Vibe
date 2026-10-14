<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 修改用户性别（docs/07 第 3.2 节，**超管专有**）。
 *
 * 性别关系到计费方向（男付女收），改错了会把钱算反，所以规则单独一个文件、必须填原因。
 */
class ClUserGenderRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'gender' => 'required|integer|in:1,2',
            'reason' => 'required|string|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'gender.required' => '必须选性别',
            'gender.in' => '性别只能是男或女',
            'reason.required' => '修改性别必须填原因',
            'reason.max' => '原因最长 200 个字',
        ];
    }
}
