<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 提交实名的入参（docs/05 第 4.8 节 `/realname/submit`）。
 *
 * 这里**只做粗略校验**（非空、字符串、长度）：身份证号的格式和校验位交给
 * `RealNameService` 判，那边错了要返回业务码 **40003**（P29 要按业务码提示），
 * 表单校验只会给 422，客户端分不清「填错了」和「请求格式不对」。
 */
class RealNameSubmitRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'real_name' => 'required|string|max:32',
            'id_card' => 'required|string|max:32',
        ];
    }

    public function attributes(): array
    {
        return [
            'real_name' => '真实姓名',
            'id_card' => '身份证号',
        ];
    }
}
