<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 关系三张表（docs/04 第 2 节）：关注、拉黑、访客。
     */
    public function up(): void
    {
        Schema::create('cl_follow', function (Blueprint $table) {
            $table->comment('关注（docs/04 第 2 节）：双方都关注 = 互关（好友）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('关注者');
            $table->bigInteger('target_id')->comment('被关注者');
            $table->datetimes();
            $table->unique(['user_id', 'target_id'], 'cl_follow_unique');
            $table->index(['target_id', 'created_at'], 'cl_follow_target_index');
        });

        Schema::create('cl_block', function (Blueprint $table) {
            $table->comment('拉黑（docs/04 第 2 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('拉黑发起人');
            $table->bigInteger('target_id')->comment('被拉黑的人');
            $table->datetimes();
            $table->unique(['user_id', 'target_id'], 'cl_block_unique');
        });

        Schema::create('cl_visit', function (Blueprint $table) {
            $table->comment('访客（docs/04 第 2 节）：同一人一天只算一条');
            $table->bigIncrements('id');
            $table->bigInteger('visitor_id')->comment('访客');
            $table->bigInteger('target_id')->comment('被访问的人');
            $table->date('visit_date')->comment('访问日期');
            $table->dateTime('last_visit_at')->comment('最近一次访问时间');
            $table->dateTime('read_at')->nullable()->comment('被访问的人读过的位置（null = 未读）');
            $table->datetimes();
            $table->unique(['visitor_id', 'target_id', 'visit_date'], 'cl_visit_unique');
            $table->index(['target_id', 'visit_date'], 'cl_visit_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_visit');
        Schema::dropIfExists('cl_block');
        Schema::dropIfExists('cl_follow');
    }
};
