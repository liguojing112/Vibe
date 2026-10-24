<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * AI 代聊：女用户账号的人设配置 + 每次调用模型的成本留痕。
 *
 * 一个女用户最多一套人设（`user_id` 唯一）。`enabled` 是总开关，
 * `proactive` 控制是否主动找人搭话（0 关闭 / 1 低 / 2 中 / 3 高）。
 *
 * 成本单独一张日志表：出问题要能查到「哪条消息花了多少 token」，
 * 人设表上只存累计值用于列表展示。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_ai_persona', static function (Blueprint $table): void {
            $table->comment('AI 代聊人设（女用户）');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('对应的女用户');
            $table->tinyInteger('enabled')->default(0)->comment('1 = 开启自动聊天');
            $table->string('persona', 1000)->default('')->comment('性格与背景（自由文本）');
            $table->string('tone', 300)->default('')->comment('说话风格');
            $table->string('taboos', 300)->default('')->comment('不聊的话题');
            $table->tinyInteger('proactive')->default(0)->comment('主动搭话：0关闭 1低 2中 3高');
            $table->tinyInteger('active_start')->default(8)->comment('活跃开始小时（0-23）');
            $table->tinyInteger('active_end')->default(23)->comment('活跃结束小时（0-23）');
            $table->smallInteger('daily_limit')->default(80)->comment('每日自动回复上限');
            $table->smallInteger('replied_today')->default(0)->comment('今天已自动回复条数');
            $table->date('replied_on')->nullable()->comment('上面那个计数属于哪一天（跨天重置）');
            $table->bigInteger('prompt_tokens')->default(0)->comment('累计输入 token');
            $table->bigInteger('completion_tokens')->default(0)->comment('累计输出 token');
            $table->tinyInteger('status')->default(1)->comment('1 正常 / 2 暂停');
            $table->datetimes();

            $table->unique('user_id', 'cl_ai_persona_user_unique');
            $table->index(['enabled', 'status'], 'cl_ai_persona_enabled_index');
        });

        Schema::create('cl_ai_reply_log', static function (Blueprint $table): void {
            $table->comment('AI 代聊调用记录（成本留痕）');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('persona_id')->comment('人设');
            $table->bigInteger('user_id')->comment('女用户');
            $table->bigInteger('to_user_id')->comment('回复给谁');
            $table->bigInteger('conversation_id')->nullable()->comment('会话');
            $table->bigInteger('message_id')->nullable()->comment('生成的那条消息');
            $table->string('kind', 16)->default('reply')->comment('reply 自动回复 / proactive 主动搭话');
            $table->integer('prompt_tokens')->default(0)->comment('输入 token');
            $table->integer('completion_tokens')->default(0)->comment('输出 token');
            $table->datetimes();

            $table->index(['persona_id', 'id'], 'cl_ai_reply_log_persona_index');
            $table->index('created_at', 'cl_ai_reply_log_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_ai_reply_log');
        Schema::dropIfExists('cl_ai_persona');
    }
};
