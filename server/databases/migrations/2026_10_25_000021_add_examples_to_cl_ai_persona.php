<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * AI 代聊人设加「对话示例」（few-shot）。
 *
 * 只写抽象要求（「自然一点」「短句」）模型学不会，给它看几组真实对话它就知道
 * 该用什么长度、什么标点、什么语气 —— 这是角色扮演类提示词里最有效的一招。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_ai_persona', static function (Blueprint $table): void {
            $table->text('examples')->nullable()->after('taboos')->comment('对话示例（few-shot），几组问答');
        });
    }

    public function down(): void
    {
        Schema::table('cl_ai_persona', static function (Blueprint $table): void {
            $table->dropColumn('examples');
        });
    }
};
