<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 互聊规则增加「自动发送」开关（客户 2026-11-10）。
 *
 * 客户要求「男 AI 什么时候发消息要在后台控制」，所以把定时任务的自动发送
 * 和后台的「立即发送」按钮拆成两件事：
 * - `auto_send = 1`：定时任务按规则自己发（原来的行为）；
 * - `auto_send = 0`：定时任务不碰这条规则，只能后台点按钮手动发一次。
 *
 * 默认 1，老数据保持原行为。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->tinyInteger('auto_send')->default(1)->after('enabled')
                ->comment('1 = 定时任务自动发；0 = 只能后台手动点发送');
        });
    }

    public function down(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->dropColumn('auto_send');
        });
    }
};
