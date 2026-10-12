<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 关系类接口的入参（docs/05 第 4.4 节）：关注 / 取关 / 拉黑 / 解除拉黑都是传 `target_id`。
 */
class RelationTargetRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'target_id' => 'required|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'target_id' => '目标用户',
        ];
    }
}
