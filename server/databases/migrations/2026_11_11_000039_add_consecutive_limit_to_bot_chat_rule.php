<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 同一个男号最多连发几条（客户 2026-11-11）。
 *
 * 收益明细里同一个男号连着刷 3 条 +15，客户觉得假。
 * 到了连发上限就换个人接，过一会儿原来那个人还能回来。
 *
 * 默认 2：允许「一来一回」的连续两条，但不会出现三条同一个名字。
 * 0 = 不限制（老行为）。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->tinyInteger('max_consecutive_messages')->default(2)->after('male_daily_limit')
                ->comment('同一个男号最多连发几条，0 = 不限');
        });
    }

    public function down(): void
    {
        Schema::table('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->dropColumn('max_consecutive_messages');
        });
    }
};
