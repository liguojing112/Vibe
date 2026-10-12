<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 标记会话已读（docs/05 第 4.5 节 `POST /chat/read`）。
 */
class ChatReadRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'conversation_id' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'conversation_id' => '会话',
        ];
    }
}
