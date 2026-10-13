<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 会员套餐与会员订单（docs/04 第 5 节）。
 *
 * `cl_vip_order` 把**开通那一刻的到期时间前后**都记下来：
 * 续费是在原到期时间上顺延的，只存一个 `expire_after` 的话事后对不上账
 * （「这次到底加了 30 天还是 45 天」）。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_vip_plan', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->string('name', 16)->comment('套餐名');
            $table->integer('days')->comment('天数');
            $table->integer('price_coins')->comment('价格（金币）');
            $table->integer('sort')->default(0)->comment('排序，越小越前');
            $table->tinyInteger('enabled')->default(1)->comment('1 上架 0 下架');
            $table->datetimes();

            $table->index(['enabled', 'sort']);
        });

        Schema::create('cl_vip_order', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->string('order_no', 64)->comment('订单号（客户端订单号；后台开通时自动生成）');
            $table->bigInteger('user_id')->comment('开通人（男）');
            $table->bigInteger('plan_id')->nullable()->comment('套餐 id（后台直接填天数时为 null）');
            $table->integer('days')->comment('本次开通天数');
            $table->integer('price_coins')->default(0)->comment('本次扣的金币（后台开通为 0）');
            $table->tinyInteger('source')->comment('1 金币购买 2 后台开通');
            $table->dateTime('expire_before')->nullable()->comment('开通前的到期时间（没过期才有）');
            $table->dateTime('expire_after')->comment('开通后的到期时间');
            $table->bigInteger('operator_id')->nullable()->comment('后台操作人');
            $table->string('remark', 255)->nullable()->comment('后台备注');
            $table->datetimes();

            // 幂等：同一个订单号只能开一次会员
            $table->unique('order_no');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_vip_order');
        Schema::dropIfExists('cl_vip_plan');
    }
};
