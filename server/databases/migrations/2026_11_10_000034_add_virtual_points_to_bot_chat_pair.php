<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 15 元/天的上限改成**按「男号 + 女号」这一对**算（客户 2026-11-10 确认）。
 *
 * 原来记在 `cl_bot_chat_target`（规则 + 女号）上：同一条规则下几个男号跟同一个女号聊，
 * 收益会被加在一起，谁也没超过 15 元、这个女号却被整体停了。
 *
 * 现在记在配对上：每一对各自 15 元，A 到顶了换 B 继续，互不影响。
 * 配对表本来就是「按天一行」（`stat_date` 是唯一键的一部分），天然每天重置。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_bot_chat_pair', static function (Blueprint $table): void {
            $table->bigInteger('virtual_points_today')->default(0)->after('rounds_today')
                ->comment('这一对今天累计虚拟积分（不写入钱包），到规则上限就停这一对');
        });
    }

    public function down(): void
    {
        Schema::table('cl_bot_chat_pair', static function (Blueprint $table): void {
            $table->dropColumn('virtual_points_today');
        });
    }
};
