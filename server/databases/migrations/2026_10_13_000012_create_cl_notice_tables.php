<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 系统通知与互动通知（docs/04 第 6 节）。
 *
 * **升级、实名结果、提现结果、封号**这些是「一对一的通知」，直接写 `read_at`；
 * `user_id = 0` 是**全员公告**，一个人读没读不能写在这张表上，所以另开 `cl_notice_read`
 * 记「谁读过哪条公告」。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_notice', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('收件人；0 = 全员公告');
            $table->tinyInteger('category')->comment('1 系统通知 2 互动');
            $table->string('title', 64)->comment('标题');
            $table->string('content', 500)->default('')->comment('正文');
            $table->string('biz_type', 20)->nullable()->comment('PROFILE_AUDIT / REAL_NAME / WITHDRAW / LEVEL_UP / BAN / FOLLOW / GIFT / ANNOUNCE');
            $table->bigInteger('biz_id')->nullable()->comment('关联业务 id');
            $table->dateTime('read_at')->nullable()->comment('读过的时间（全员公告不用这一列）');
            $table->datetimes();

            $table->index(['user_id', 'category', 'id']);
            $table->index('biz_type');
        });

        Schema::create('cl_notice_read', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('notice_id')->comment('公告 id');
            $table->bigInteger('user_id')->comment('谁读的');
            $table->datetimes();

            $table->unique(['notice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_notice_read');
        Schema::dropIfExists('cl_notice');
    }
};
