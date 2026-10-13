<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 申请提现的入参（docs/05 第 4.8 节 `/withdraw/apply`）。
 *
 * 档位、实名、次数这些**不在表单里校验**：它们要读配置和数据库，
 * 而且错了要返回业务码（40001/20002）让页面说人话，表单校验只会给 422。
 */
class WithdrawApplyRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'amount_yuan' => 'required|integer|min:1',
            'account_type' => 'required|string|in:ALIPAY,WECHAT,BANK',
            'account_no' => 'required|string|max:64',
            'bank_name' => 'sometimes|nullable|string|max:64',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount_yuan' => '提现金额',
            'account_type' => '收款方式',
            'account_no' => '收款账号',
            'bank_name' => '开户行',
        ];
    }
}
