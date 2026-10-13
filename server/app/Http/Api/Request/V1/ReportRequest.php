<?php

declare(strict_types=1);

namespace App\Http\Api\Request\V1;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/**
 * 举报的入参（docs/05 第 4.9 节 `/report`、`docs/01` P05 的弹窗）。
 *
 * 原因只认 5 种，写错立刻 422；补充说明和截图都可选。
 */
class ReportRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'target_id' => 'required|integer|min:1',
            'reason' => 'required|string|in:PORN,FRAUD,ABUSE,FAKE,OTHER',
            'content' => 'sometimes|nullable|string|max:500',
            'images' => 'sometimes|array|max:9',
            'images.*' => 'string|max:255',
            'message_id' => 'sometimes|nullable|integer|min:1',
        ];
    }

    public function attributes(): array
    {
        return [
            'target_id' => '举报对象',
            'reason' => '举报原因',
            'content' => '补充说明',
            'images' => '截图',
            'message_id' => '消息',
        ];
    }
}
