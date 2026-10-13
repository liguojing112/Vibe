<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 送礼物的入参（docs/05 第 4.6 节）。
 *
 * `client_order_id` 必填：服务端靠它做幂等，网络重试不会重复扣钱。
 */
class GiftSendRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'receiver_id' => 'required|integer|min:1',
            'gift_id' => 'required|integer|min:1',
            'client_order_id' => 'required|string|max:64',
        ];
    }

    public function attributes(): array
    {
        return [
            'receiver_id' => '收礼人',
            'gift_id' => '礼物',
            'client_order_id' => '订单号',
        ];
    }
}
