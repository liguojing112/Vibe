<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 提现单与收款信息（docs/04 第 4 节）。
 *
 * 金额一律存**分**（`amount_fen` / `fee_fen` / `actual_fen`），积分存 `points` ——
 * 手续费要「保留两位小数、按分向下取整」，用分算才不会出现浮点误差。
 *
 * `account_name` 是**实名姓名**（打款要跟实名一致，docs/06 第 9.1 节第 8 步）。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_withdraw_order', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->string('order_no', 32)->unique()->comment('提现单号');
            $table->bigInteger('user_id')->comment('申请人（女）');
            $table->bigInteger('amount_fen')->comment('申请金额（分）');
            $table->bigInteger('points')->comment('冻结的积分');
            $table->bigInteger('fee_fen')->default(0)->comment('手续费（分）');
            $table->bigInteger('actual_fen')->comment('实际到账（分）');
            $table->string('account_type', 16)->comment('ALIPAY / WECHAT / BANK');
            $table->string('account_no', 64)->comment('收款账号');
            $table->string('account_name', 32)->comment('收款人（= 实名姓名）');
            $table->string('bank_name', 64)->nullable()->comment('开户行（银行卡才要）');
            $table->tinyInteger('status')->default(0)->comment('0 PENDING 1 APPROVED 2 REJECTED 3 PAID');
            $table->string('reject_reason', 255)->nullable()->comment('驳回原因');
            $table->string('pay_proof', 255)->nullable()->comment('打款流水号 / 备注');
            $table->bigInteger('reviewer_id')->nullable()->comment('审核人');
            $table->bigInteger('payer_id')->nullable()->comment('打款人');
            $table->dateTime('reviewed_at')->nullable()->comment('审核时间');
            $table->dateTime('paid_at')->nullable()->comment('打款时间');
            $table->datetimes();

            $table->index(['user_id', 'id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('cl_withdraw_account', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('用户 id');
            $table->string('account_type', 16)->comment('ALIPAY / WECHAT / BANK');
            $table->string('account_no', 64)->comment('收款账号');
            $table->string('bank_name', 64)->nullable()->comment('开户行');
            $table->datetimes();

            // 一个人只存一份「上次用的收款信息」
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_withdraw_account');
        Schema::dropIfExists('cl_withdraw_order');
    }
};
