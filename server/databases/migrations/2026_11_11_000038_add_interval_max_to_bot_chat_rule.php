<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

/**
 * 男号两次消息之间改成「区间随机」（客户 2026-11-11）。
 *
 * 原来只有 `interval_seconds` 一个值（±30% 抖动）。配成 30 秒时，
 * 同一个男号一分钟能发 3 条，女号的收益明细里同一个名字连着刷 —— 客户觉得太假。
 *
 * 现在加一个上限：实际间隔在 `[interval_seconds, interval_max_seconds]` 之间随机。
 * 存量规则统一改成 2~5 分钟；`session_gap_seconds` 同步拉到 10 分钟，
 * 否则间隔取到 5 分钟时刚好撞上「会话断开」，对话会被误判成冷场而换人。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->integer('interval_max_seconds')->default(0)->after('interval_seconds')
                ->comment('同一对两次消息的最大间隔（秒），0 = 不设上限，沿用 ±30% 抖动');
        });

        Db::table('cl_bot_chat_rule')->update([
            'interval_seconds' => 120,
            'interval_max_seconds' => 300,
            'session_gap_seconds' => 600,
        ]);
    }

    public function down(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->dropColumn('interval_max_seconds');
        });
    }
};
