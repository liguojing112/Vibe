<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 刷新 token（docs/05 第 4.1 节）：refresh_token 放 body 或 Authorization 头都可以。
 */
class RefreshRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'refresh_token' => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return ['refresh_token' => '刷新令牌'];
    }
}
