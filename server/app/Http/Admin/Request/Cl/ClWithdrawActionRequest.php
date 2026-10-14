<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 驳回提现（原因必填，女方在 P28 看得到） / 标记已打款（打款流水号或备注必填）。 */
class ClWithdrawActionRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:200',
            'pay_proof' => 'nullable|string|max:200',
        ];
    }
}
