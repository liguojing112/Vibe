<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cl_coin_log', function (Blueprint $table) {
            $table->comment('金币流水（docs/04 第 4 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('用户 ID');
            $table->string('type', 16)->comment('RECHARGE / MESSAGE / GIFT / VIP / TASK / ADMIN_DEDUCT');
            $table->bigInteger('change')->comment('正数增加，负数减少');
            $table->bigInteger('balance_after')->comment('变动后余额');
            $table->string('ref_type', 16)->comment('message / gift_record / vip_order / task_log / recharge_order / admin');
            $table->bigInteger('ref_id')->comment('关联单号');
            $table->string('title', 64)->comment('App 显示的标题，例如「私信 · 小满」');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->bigInteger('operator_id')->nullable()->comment('后台操作员');
            $table->datetimes();
            $table->index(['user_id', 'id'], 'cl_coin_log_user_index');
            $table->index(['type', 'created_at'], 'cl_coin_log_type_index');
            // 防重复记账：同一笔业务只能有一条流水
            $table->unique(['type', 'ref_type', 'ref_id'], 'cl_coin_log_ref_unique');
        });

        Schema::create('cl_point_log', function (Blueprint $table) {
            $table->comment('积分流水（docs/04 第 4 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('用户 ID');
            $table->string('type', 20)->comment('MESSAGE_INCOME / GIFT_INCOME / TASK / WITHDRAW_FREEZE / WITHDRAW_REFUND / WITHDRAW_PAID / ADMIN_ADJUST');
            $table->bigInteger('change')->comment('可用积分变化');
            $table->bigInteger('frozen_change')->comment('冻结积分变化');
            $table->bigInteger('balance_after')->comment('变动后可用积分');
            $table->bigInteger('frozen_after')->comment('变动后冻结积分');
            $table->string('ref_type', 16)->comment('message / gift_record / task_log / withdraw_order / admin');
            $table->bigInteger('ref_id')->comment('关联单号');
            $table->string('title', 64)->comment('App 显示的标题，例如「私信收益 · 阿宁」');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->bigInteger('operator_id')->nullable()->comment('后台操作员');
            $table->datetimes();
            $table->index(['user_id', 'id'], 'cl_point_log_user_index');
            $table->index(['type', 'created_at'], 'cl_point_log_type_index');
            $table->unique(['type', 'ref_type', 'ref_id'], 'cl_point_log_ref_unique');
        });

        Schema::create('cl_platform_income', function (Blueprint $table) {
            $table->comment('平台收入明细（docs/04 第 4 节）');
            $table->bigIncrements('id');
            $table->string('source', 16)->comment('MESSAGE / GIFT / VIP');
            $table->bigInteger('ref_id')->comment('来源单据 ID');
            $table->bigInteger('payer_id')->comment('付费方（男用户）');
            $table->bigInteger('receiver_id')->nullable()->comment('收益方（女用户，礼物/私信才有）');
            $table->bigInteger('coins')->comment('男方支付金币');
            $table->bigInteger('receiver_points')->comment('女方所得积分');
            $table->date('income_date')->comment('归属日期（按天汇总）');
            $table->datetimes();
            $table->index(['income_date', 'source'], 'cl_platform_income_date_source_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cl_platform_income');
        Schema::dropIfExists('cl_point_log');
        Schema::dropIfExists('cl_coin_log');
    }
};
