<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 领取任务奖励的入参（docs/05 第 4.9 节 `/task/claim`）。
 */
class TaskClaimRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'task_id' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'task_id' => '任务',
        ];
    }
}
