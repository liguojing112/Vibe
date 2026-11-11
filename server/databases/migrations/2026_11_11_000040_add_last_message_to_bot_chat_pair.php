<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 配对表记一下最后一条消息的 id（2026-11-11）。
 *
 * `last_sent_at` 只精确到秒：两条消息落在同一秒时，「这个女号最后跟谁聊的」
 * 就变成随机结果 —— 表现为刚换的人下一秒又被换回去。
 * 用消息 id 排序就不会有歧义（自增、严格递增）。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_bot_chat_pair', static function (Blueprint $table): void {
            $table->bigInteger('last_message_id')->nullable()->after('last_sent_at')
                ->comment('这一对最后一条消息 id，用来精确判断「最后跟谁聊的」');
        });
    }

    public function down(): void
    {
        Schema::table('cl_bot_chat_pair', static function (Blueprint $table): void {
            $table->dropColumn('last_message_id');
        });
    }
};
