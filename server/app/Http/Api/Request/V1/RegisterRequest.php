<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 注册（docs/01 P00）：手机号 11 位、密码 6~20 位且必须含字母和数字、昵称 2~12 字。
 */
class RegisterRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'phone' => 'required|regex:/^1[3-9]\d{9}$/',
            'password' => 'required|string|min:6|max:20|regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
            'confirm_password' => 'nullable|same:password',
            'gender' => 'required|integer|in:1,2',
            'nickname' => 'required|string|min:2|max:12',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => '手机号',
            'password' => '密码',
            'confirm_password' => '确认密码',
            'gender' => '性别',
            'nickname' => '昵称',
        ];
    }
}
