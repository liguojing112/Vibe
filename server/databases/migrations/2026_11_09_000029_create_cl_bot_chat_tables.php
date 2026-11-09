<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

/**
 * AI 男女互聊：机器人男号给指定女号发消息。
 *
 * 四张表各自解决一件事：
 * - `cl_bot_chat_rule`：规则本体（谁发给谁、节奏、上限）；
 * - `cl_bot_chat_target`：目标女号 + 当日的虚拟收益（跨天按 `stat_date` 重置）；
 * - `cl_bot_chat_pair`：每对男女当日的轮数（每对每天最多几个来回）；
 * - `cl_bot_chat_log`：每一条实际发出去的记录，出问题能查。
 *
 * 女号收益**只在这里计数**，不进 `cl_point_wallet`：这条链路不产生任何真实资金流动。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_bot_chat_rule', static function (Blueprint $table): void {
            $table->comment('AI 男女互聊规则（机器人男号 → 指定女号）');
            $table->bigIncrements('id')->comment('主键');
            $table->string('name', 64)->default('')->comment('规则名称');
            $table->tinyInteger('enabled')->default(0)->comment('1 启用 0 停用');
            $table->json('male_user_ids')->comment('手动指定的男号 id 列表');
            $table->integer('pool_male_count')->default(0)->comment('从机器人号池随机补足的男号数量，0 = 不补');
            $table->tinyInteger('window_start')->default(0)->comment('每日时段开始小时（0-23）');
            $table->tinyInteger('window_end')->default(0)->comment('每日时段结束小时（0-23），与 start 相同表示全天');
            $table->integer('round_limit_per_pair')->default(20)->comment('每对男女每天最多几个来回');
            $table->integer('interval_seconds')->default(30)->comment('同一对之间最小发送间隔（秒）');
            $table->integer('daily_amount_limit_fen')->default(1500)->comment('每个女号每天虚拟收益上限（分），默认 1500 = 15 元');
            $table->integer('context_messages')->default(10)->comment('生成时带多少条历史消息');
            $table->bigInteger('operator_id')->nullable()->comment('创建的后台账号');
            $table->datetimes();

            $table->index(['enabled'], 'cl_bot_chat_rule_enabled_index');
        });

        Schema::create('cl_bot_chat_target', static function (Blueprint $table): void {
            $table->comment('AI 互聊的目标女号（含当日虚拟收益，跨天重置）');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('rule_id')->comment('所属规则');
            $table->bigInteger('female_id')->comment('目标女号');
            $table->tinyInteger('enabled')->default(1)->comment('1 参与 0 暂停');
            $table->date('stat_date')->nullable()->comment('下面三个计数属于哪一天（跨天重置的依据）');
            $table->bigInteger('virtual_points_today')->default(0)->comment('今日累计虚拟积分（不写入钱包）');
            $table->integer('rounds_today')->default(0)->comment('今日已完成来回数（冗余展示用）');
            $table->string('stopped_reason', 32)->nullable()->comment('daily_limit = 当日已达收益上限');
            $table->dateTime('last_sent_at')->nullable()->comment('最后一条发送时间');
            $table->datetimes();

            $table->unique(['rule_id', 'female_id'], 'cl_bot_chat_target_unique');
            $table->index(['female_id'], 'cl_bot_chat_target_female_index');
        });

        Schema::create('cl_bot_chat_pair', static function (Blueprint $table): void {
            $table->comment('AI 互聊：每对男女当日的轮数与发送时间');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('rule_id')->comment('所属规则');
            $table->bigInteger('male_id')->comment('男号');
            $table->bigInteger('female_id')->comment('女号');
            $table->date('stat_date')->comment('这条记录属于哪一天');
            $table->integer('rounds_today')->default(0)->comment('今日已完成来回数');
            $table->dateTime('last_sent_at')->nullable()->comment('最后一条发送时间');
            $table->datetimes();

            $table->unique(['rule_id', 'male_id', 'female_id', 'stat_date'], 'cl_bot_chat_pair_unique');
            $table->index(['rule_id', 'female_id', 'stat_date'], 'cl_bot_chat_pair_female_index');
        });

        Schema::create('cl_bot_chat_log', static function (Blueprint $table): void {
            $table->comment('AI 互聊发送记录（出问题能查到哪一条、为什么失败）');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('rule_id')->comment('规则');
            $table->bigInteger('male_id')->comment('男号');
            $table->bigInteger('female_id')->comment('女号');
            $table->bigInteger('message_id')->nullable()->comment('落库后的 cl_message.id');
            $table->string('role', 16)->default('male_bot')->comment('male_bot 男号发起 / female_ai 女 AI 回复');
            $table->integer('virtual_points')->default(0)->comment('本条产生的虚拟积分（女 AI 回复为 0）');
            $table->tinyInteger('status')->default(1)->comment('1 成功 2 失败');
            $table->string('fail_reason', 255)->nullable()->comment('失败原因');
            $table->datetimes();

            $table->index(['rule_id', 'id'], 'cl_bot_chat_log_rule_index');
            $table->index(['female_id', 'created_at'], 'cl_bot_chat_log_female_index');
        });

        // 女号人设表原来只服务女号；男号人设复用同一张表，只改注释说明用途（不改结构）。
        Db::statement("ALTER TABLE `cl_ai_persona` COMMENT = 'AI 人设（男女通用：女号用于自动回复，男号只用人设字段）'");

        // 全局开关：关掉后定时任务一条都不发
        Db::table('cl_config')->updateOrInsert(
            ['key' => 'bot_chat.enabled'],
            [
                'key' => 'bot_chat.enabled',
                'value' => '0',
                'value_type' => 'bool',
                'group' => 'ai',
                'label' => 'AI 互聊总开关',
                'remark' => '关闭后定时任务不再产生任何机器人消息',
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ],
        );
    }

    public function down(): void
    {
        Db::table('cl_config')->where('key', 'bot_chat.enabled')->delete();
        Db::statement("ALTER TABLE `cl_ai_persona` COMMENT = 'AI 代聊人设（女用户）'");
        Schema::dropIfExists('cl_bot_chat_log');
        Schema::dropIfExists('cl_bot_chat_pair');
        Schema::dropIfExists('cl_bot_chat_target');
        Schema::dropIfExists('cl_bot_chat_rule');
    }
};
