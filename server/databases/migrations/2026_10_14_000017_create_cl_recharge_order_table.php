<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 后台充值单（docs/04 第 4 节、docs/06 第 8 节）。
 *
 * 钱一律存**分**（`amount_fen`），金币存 `coins` —— 和提现单一个口径。
 * 充值是客服手工确认收款之后在后台录入的（App 里没有自助支付，docs/06 第 2.6 节）。
 * `operator_id` 记的是哪个后台账号录的，出问题要能倒查到人。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_recharge_order', static function (Blueprint $table): void {
            $table->comment('后台充值单');
            $table->bigIncrements('id')->comment('主键');
            $table->string('order_no', 32)->unique()->comment('充值单号');
            $table->bigInteger('user_id')->comment('充值给谁（只能是男用户）');
            $table->bigInteger('coins')->comment('充值金币数');
            $table->bigInteger('amount_fen')->default(0)->comment('实收金额（分）；0 = 赠送');
            $table->string('pay_channel', 16)->comment('WECHAT / ALIPAY / OTHER / GIFT');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->bigInteger('operator_id')->nullable()->comment('操作的后台账号');
            $table->datetimes();

            $table->index(['user_id', 'id'], 'cl_recharge_order_user_index');
            $table->index('created_at', 'cl_recharge_order_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_recharge_order');
    }
};
