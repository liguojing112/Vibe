<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * App 在线充值下单（档位下标）。
 *
 * 只收下标：金额和金币都由服务端按 `recharge.packs` 取，避免客户端改价。
 */
class CreateRechargeOrderRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'pack_index' => 'required|integer|min:0',
        ];
    }

    public function attributes(): array
    {
        return [
            'pack_index' => '充值档位',
        ];
    }
}
