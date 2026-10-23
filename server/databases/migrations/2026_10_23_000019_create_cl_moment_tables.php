<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 朋友圈（动态）、点赞、评论。
 *
 * 图片存 JSON 数组而不是子表：最多 9 张、不需要单图审核，列表查询能一次取完。
 * 点赞数和评论数是**冗余计数**，在事务里原子更新（列表页不做 N+1 的 COUNT），
 * 对账脚本见 `scripts/recount-moments.php`。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_moment', static function (Blueprint $table): void {
            $table->comment('朋友圈动态');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('作者');
            $table->string('content', 500)->default('')->comment('正文，可为空（纯图）');
            $table->json('images')->nullable()->comment('图片 URL 数组，最多 9 张');
            $table->integer('like_count')->unsigned()->default(0)->comment('点赞数（冗余）');
            $table->integer('comment_count')->unsigned()->default(0)->comment('评论数（冗余）');
            $table->tinyInteger('status')->default(1)->comment('1 正常 / 2 后台隐藏');
            $table->datetimes();
            $table->softDeletes();

            $table->index(['user_id', 'deleted_at', 'id'], 'cl_moment_user_index');
            $table->index(['status', 'id'], 'cl_moment_status_index');
        });

        Schema::create('cl_moment_like', static function (Blueprint $table): void {
            $table->comment('朋友圈点赞');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('moment_id')->comment('动态');
            $table->bigInteger('user_id')->comment('谁点的');
            $table->datetimes();

            $table->unique(['moment_id', 'user_id'], 'cl_moment_like_unique');
            $table->index(['user_id', 'id'], 'cl_moment_like_user_index');
        });

        Schema::create('cl_moment_comment', static function (Blueprint $table): void {
            $table->comment('朋友圈评论');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('moment_id')->comment('动态');
            $table->bigInteger('user_id')->comment('评论人');
            $table->string('content', 500)->comment('评论内容');
            $table->tinyInteger('status')->default(1)->comment('1 正常 / 2 后台隐藏');
            $table->datetimes();
            $table->softDeletes();

            $table->index(['moment_id', 'deleted_at', 'id'], 'cl_moment_comment_moment_index');
            $table->index(['user_id', 'id'], 'cl_moment_comment_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_moment_comment');
        Schema::dropIfExists('cl_moment_like');
        Schema::dropIfExists('cl_moment');
    }
};
