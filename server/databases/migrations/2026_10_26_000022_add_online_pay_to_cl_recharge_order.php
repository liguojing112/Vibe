<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 在线支付（万世码支付 / 易支付 epay 协议）。
 *
 * 原来的 `cl_recharge_order` 只记录客服人工确认后的充值，所以没有状态。
 * 接入在线支付后需要一张单从「待支付」走到「已支付」：
 * - `source` 区分人工 / 在线；
 * - `status` 0 待支付 1 已支付 2 已关闭；
 * - `trade_no` 存码支付订单号，`pay_url` 存取到的支付链接（二维码内容），
 *   `paid_at` 存支付完成时间。
 *
 * 历史人工充值单默认 `source=ADMIN`、`status=1`，语义不变。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cl_recharge_order', static function (Blueprint $table): void {
            $table->string('source', 16)->default('ADMIN')->after('pay_channel')->comment('来源：ADMIN 人工 / ONLINE 在线支付');
            $table->unsignedTinyInteger('status')->default(1)->after('source')->comment('0 待支付 / 1 已支付 / 2 已关闭');
            $table->string('trade_no', 64)->nullable()->after('status')->comment('码支付订单号');
            $table->text('pay_url')->nullable()->after('trade_no')->comment('支付链接（二维码内容）');
            $table->dateTime('paid_at')->nullable()->after('pay_url')->comment('支付完成时间');

            $table->index(['status', 'id'], 'cl_recharge_order_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('cl_recharge_order', static function (Blueprint $table): void {
            $table->dropIndex('cl_recharge_order_status_index');
            $table->dropColumn(['source', 'status', 'trade_no', 'pay_url', 'paid_at']);
        });
    }
};
