<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 举报表（docs/04 第 3 节）。
 *
 * 这张表本来属于 P6 的 T6.4（`/report` 接口 + 后台举报处理），但 **T4.9 的消息清理要用到它**：
 * `cl_message` 的保留规则是「被 `cl_report.message_id` 关联的消息长期保留」（监管/取证），
 * 没有这张表这条规则就没法实现也没法测。所以先把表建出来，T6.4 直接在它上面做接口。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_report', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('reporter_id')->comment('举报人');
            $table->bigInteger('target_id')->comment('被举报人');
            $table->string('reason', 16)->comment('PORN / FRAUD / ABUSE / FAKE / OTHER');
            $table->string('content', 500)->default('')->comment('补充说明');
            $table->json('images')->nullable()->comment('截图地址');
            $table->bigInteger('message_id')->nullable()->comment('从聊天里举报时带上');
            $table->tinyInteger('status')->default(0)->comment('0 待处理 1 已处理-违规 2 已处理-不违规');
            $table->string('handle_result', 255)->nullable()->comment('处理结果');
            $table->bigInteger('handler_id')->nullable()->comment('处理人（后台管理员）');
            $table->dateTime('handled_at')->nullable()->comment('处理时间');
            $table->datetimes();

            $table->index('reporter_id');
            $table->index('target_id');
            // 消息清理要用它反查「这条消息被举报过没有」
            $table->index('message_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_report');
    }
};
