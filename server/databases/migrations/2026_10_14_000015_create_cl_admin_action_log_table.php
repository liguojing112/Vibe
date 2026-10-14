<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 后台对某个用户的动作流水（docs/07 第 2 节抽屉里的「封禁记录」、docs/06 第 5 节的调等级日志）。
 *
 * 为什么要单独一张表：
 * - `cl_user` 上只有**当前**封禁状态，历史在哪封的、谁封的查不到；
 * - MineAdmin 自带的 `user_operation_log` 只记「谁在什么时候调了哪个接口」，**不存请求体和目标用户**，
 *   所以「从几级调到几级、原因是什么」这类信息在里面查不出来。
 *
 * 一张表覆盖两类需求：封禁记录按 `action in (BAN, UNBAN)` 过滤，调等级那类看 `detail`。
 * `detail` 存**给人看的一句话**（带前后值），不存 JSON —— 这张表的用途就是「事后能说清楚」。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_admin_action_log', static function (Blueprint $table): void {
            $table->comment('后台对用户的动作流水');
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('被操作的用户');
            $table->string('action', 32)->comment('BAN / UNBAN / LEVEL / VIP / GENDER / PROFILE / PASSWORD');
            $table->string('detail', 255)->comment('给人看的一句话（含前后值）');
            $table->bigInteger('operator_id')->nullable()->comment('后台账号 id');
            $table->string('operator_name', 32)->default('')->comment('后台账号名（账号删了也看得见）');
            $table->datetimes();

            $table->index(['user_id', 'action'], 'cl_admin_action_log_user_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_admin_action_log');
    }
};
