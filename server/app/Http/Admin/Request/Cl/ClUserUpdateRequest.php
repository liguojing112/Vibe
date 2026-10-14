<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 后台直接改用户资料（docs/07 第 3.2 节）：昵称 / 头像 / 简介，**绕过审核**。
 *
 * 三个都可以不传，只改传了的那个；但一个都不传就没意义了（服务层会挡）。
 */
class ClUserUpdateRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'nickname' => 'nullable|string|max:32',
            'avatar' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nickname.max' => '昵称最长 32 个字',
            'avatar.max' => '头像地址太长了',
            'bio.max' => '自我介绍最长 255 个字',
        ];
    }
}
