<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 提现记录的查询参数（docs/05 第 4.8 节 `/withdraw/records`）。只分页。
 */
class WithdrawRecordsRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'page' => '页码',
        ];
    }
}
