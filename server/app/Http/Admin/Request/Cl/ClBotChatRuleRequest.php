<?php

declare(strict_types=1);

namespace App\Http\Admin\Request\Cl;

use App\Http\Common\Request\Traits\NoAuthorizeTrait;
use Hyperf\Validation\Request\FormRequest;

/** 保存 AI 互聊规则。 */
class ClBotChatRuleRequest extends FormRequest
{
    use NoAuthorizeTrait;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:64',
            'enabled' => 'nullable|integer|in:0,1',
            'auto_send' => 'nullable|integer|in:0,1',
            'male_user_ids' => 'nullable|array|max:500',
            'male_user_ids.*' => 'integer|min:1',
            'pool_male_count' => 'nullable|integer|between:0,500',
            'female_user_ids' => 'nullable|array|max:200',
            'female_user_ids.*' => 'integer|min:1',
            'window_start' => 'nullable|integer|between:0,23',
            'window_end' => 'nullable|integer|between:0,23',
            'round_limit_per_pair' => 'nullable|integer|between:1,2000',
            'male_daily_limit' => 'nullable|integer|between:0,100000',
            'interval_seconds' => 'nullable|integer|between:0,86400',
            'session_gap_seconds' => 'nullable|integer|between:60,86400',
            'daily_amount_limit_fen' => 'nullable|integer|between:1,1000000',
            'context_messages' => 'nullable|integer|between:2,50',
            'male_personas' => 'nullable|array',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '规则名称',
            'enabled' => '启用状态',
            'auto_send' => '自动发送',
            'male_user_ids' => '男号',
            'pool_male_count' => '号池补足数量',
            'female_user_ids' => '目标女号',
            'window_start' => '时段开始',
            'window_end' => '时段结束',
            'round_limit_per_pair' => '每对每天轮数',
            'male_daily_limit' => '男号每日上限',
            'interval_seconds' => '发送间隔',
            'session_gap_seconds' => '会话断开时间',
            'daily_amount_limit_fen' => '每日收益上限',
            'context_messages' => '上下文条数',
            'male_personas' => '男号人设',
        ];
    }
}
