<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 收益明细的查询参数（docs/05 第 4.8 节）。
 *
 * 只分页、不按类型过滤：P26 要求把私信收益、礼物收益、任务奖励、提现的三种状态都列出来，
 * 客户端按每条记录的 `type` 挑图标，不需要 Tab。
 */
class IncomeLogsRequest extends FormRequest
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
