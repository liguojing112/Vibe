<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 只传一批 id 的操作（比如批量删除）。 */
class ClIdsRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => '没有选中任何记录',
            'ids.max' => '一次最多处理 200 条',
        ];
    }
}
