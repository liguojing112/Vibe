<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 提交实名的入参（docs/05 第 4.8 节 `/realname/submit`）。
 *
 * 客户 2026-10-26 要求身份证号**不做格式/校验位校验**，任意非空输入都收，
 * 由审核员人工核对。这里只限制非空和长度，避免异常长的内容进库。
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
