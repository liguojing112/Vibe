<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 保存等级表（5 行）。递增、Lv1 门槛为 0 这些业务规则在 `AdminConfigService` 里。 */
class ClLevelsSaveRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1|max:5',
            'items.*.level' => 'required|integer|between:1,5',
            'items.*.threshold_points' => 'required|integer|min:0',
            'items.*.message_price' => 'required|integer|min:0',
            'items.*.message_share_rate' => 'required|integer|between:0,10000',
            'items.*.voice_price' => 'required|integer|min:0',
            'items.*.video_price' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => '没有要保存的等级',
            'items.*.level.between' => '等级只能是 1~5',
            'items.*.threshold_points.min' => '升级门槛不能是负数',
            'items.*.message_share_rate.between' => '分成比例要在 0~10000 之间（万分比）',
        ];
    }
}
