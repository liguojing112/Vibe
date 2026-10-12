<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 发私信（docs/05 第 4.5 节）：`receiver_id`、`type`（1 文字 2 图片）、`content`、`client_msg_id`。
 *
 * 字数上限这里只做一次「别太离谱」的粗校验（2000），真正的上限走配置
 * `chat.message_max_length`，在业务层判（不然改了配置这个请求类还得跟着改）。
 */
class ChatSendRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'receiver_id' => 'required|integer|min:1',
            'type' => 'required|integer|in:1,2',
            'content' => 'required|string|max:2000',
            'client_msg_id' => 'required|string|max:64',
        ];
    }

    public function attributes(): array
    {
        return [
            'receiver_id' => '接收者',
            'type' => '消息类型',
            'content' => '消息内容',
            'client_msg_id' => '客户端消息 id',
        ];
    }
}
