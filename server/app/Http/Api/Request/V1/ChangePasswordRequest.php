<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 修改密码（docs/05 第 4.1 节）：新密码规则与注册一致（6~20 位、含字母和数字）。
 */
class ChangePasswordRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|max:20|regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            'confirm_password' => 'nullable|same:new_password',
        ];
    }

    public function attributes(): array
    {
        return [
            'old_password' => '旧密码',
            'new_password' => '新密码',
            'confirm_password' => '确认密码',
        ];
    }
}
