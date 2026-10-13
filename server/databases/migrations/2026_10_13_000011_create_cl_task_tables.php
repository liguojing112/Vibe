<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 每日任务（docs/04 第 5 节、docs/06 第 7 节）。
 *
 * 三张表分工：
 * - `cl_task`     任务配置（后台可改），男女各一行、靠 `gender` 区分；
 * - `cl_task_log` 领奖流水，唯一索引 `(user_id, task_id, period_key)` 保证**同一周期只能领一次**；
 * - `cl_sign_in`  签到流水，唯一索引 `(user_id, sign_date)` 保证**一天只能签一次**，
 *                 单独一张表是为了以后算「连续签到」（设计图 11 的「连续签到可领取玫瑰」）。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_task', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->string('name', 32)->comment('任务名');
            $table->string('description', 255)->default('')->comment('说明');
            $table->string('icon', 255)->default('')->comment('图标（emoji 或图片 URL）');
            $table->string('type', 20)->comment('SIGN_IN / PROFILE_PHOTO / PROFILE_BIO / SEND_MESSAGE');
            $table->integer('target')->default(1)->comment('目标值（签到类是 1）');
            $table->integer('reward')->default(0)->comment('奖励数量（男=金币、女=积分）');
            $table->tinyInteger('gender')->default(0)->comment('适用性别 0 全部 1 男 2 女');
            $table->tinyInteger('period')->default(1)->comment('周期 1 每日 2 一次性');
            $table->integer('sort')->default(0)->comment('排序，越小越前');
            $table->tinyInteger('enabled')->default(1)->comment('1 上架 0 下架');
            $table->datetimes();

            $table->index(['gender', 'enabled', 'sort']);
            $table->index('type');
        });

        Schema::create('cl_task_log', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('领奖人');
            $table->bigInteger('task_id')->comment('任务 id');
            $table->string('period_key', 10)->comment('周期键：每日 = yyyyMMdd，一次性 = ONCE');
            $table->integer('reward')->comment('实发数量');
            $table->tinyInteger('reward_type')->comment('1 金币 2 积分');
            $table->datetimes();

            // 同一周期只能领一次（并发领两次也只发一次）
            $table->unique(['user_id', 'task_id', 'period_key']);
            $table->index('task_id');
        });

        Schema::create('cl_sign_in', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('用户 id');
            $table->date('sign_date')->comment('签到日期');
            $table->datetimes();

            $table->unique(['user_id', 'sign_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_sign_in');
        Schema::dropIfExists('cl_task_log');
        Schema::dropIfExists('cl_task');
    }
};
