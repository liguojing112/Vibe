<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 保存 AI 代聊人设。 */
class ClAiPersonaRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'enabled' => 'nullable|integer|in:0,1',
            'persona' => 'nullable|string|max:1000',
            'tone' => 'nullable|string|max:300',
            'taboos' => 'nullable|string|max:300',
            'examples' => 'nullable|string|max:2000',
            'proactive' => 'nullable|integer|between:0,3',
            'active_start' => 'nullable|integer|between:0,23',
            'active_end' => 'nullable|integer|between:0,23',
            'daily_limit' => 'nullable|integer|between:1,500',
        ];
    }

    public function attributes(): array
    {
        return [
            'enabled' => '自动回复开关',
            'persona' => '人设',
            'tone' => '说话风格',
            'taboos' => '不聊的话题',
            'proactive' => '主动程度',
            'active_start' => '活跃开始',
            'active_end' => '活跃结束',
            'daily_limit' => '每日上限',
        ];
    }
}
