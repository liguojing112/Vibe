<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;
use Mine\Support\Request\ClientIpRequestTrait;

/**
 * 登录（docs/05 第 4.1 节）：账号（手机号或系统账号）+ 密码 + 设备号。
 */
class LoginRequest extends FormRequest
{
    use ClientIpRequestTrait;
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'account' => 'required|string|max:32',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:64',
        ];
    }

    public function attributes(): array
    {
        return [
            'account' => '账号',
            'password' => '密码',
            'device_id' => '设备号',
        ];
    }
}
