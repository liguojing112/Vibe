<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 互聊规则的「像真人一样聊天」参数（客户 2026-11-10）。
 *
 * - `male_daily_limit`：**每个男号每天最多发多少条**，0 = 不限。
 *   客户要的是「男 AI 每天聊多少由后台控制」，这是按男号算的总量，
 *   和 `round_limit_per_pair`（单个男女对的上限）是两层不同的闸。
 * - `session_gap_seconds`：多久没说话就算这段对话结束。
 *   聊天要像真人 —— 同一对会连续来回聊一阵，而不是每条都换个人。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->integer('male_daily_limit')->default(0)->after('round_limit_per_pair')
                ->comment('每个男号每天最多发多少条，0 = 不限');
            $table->integer('session_gap_seconds')->default(300)->after('interval_seconds')
                ->comment('超过这么多秒没说话就算这段对话结束，换下一个男号');
        });
    }

    public function down(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->dropColumn(['male_daily_limit', 'session_gap_seconds']);
        });
    }
};
