<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 实名认证（docs/04 第 1 节）。
 *
 * 身份证号**不存明文**：`id_card_encrypted` 是 AES 密文（密钥在 `.env` 的 `CL_ID_CARD_KEY`），
 * 页面回显用 `id_card_mask`（`110***********1234`）。
 * `id_card_hash` 是 SHA-256，只用来给后台**提示**「这个身份证号别的号也用过」，
 * **不拦截** —— 一家人共用一张身份证是合理的，拦了反而是 bug。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_real_name', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->bigInteger('user_id')->comment('用户 id');
            $table->string('real_name', 32)->comment('真实姓名');
            $table->string('id_card_encrypted', 255)->comment('身份证号（AES 密文）');
            $table->string('id_card_mask', 20)->comment('打码后的号码（展示用）');
            $table->char('id_card_hash', 64)->comment('SHA-256，后台查重提示用，不拦截');
            $table->tinyInteger('status')->default(1)->comment('1 审核中 2 通过 3 驳回');
            $table->string('reject_reason', 255)->nullable()->comment('驳回原因');
            $table->bigInteger('auditor_id')->nullable()->comment('后台审核人');
            $table->dateTime('audited_at')->nullable()->comment('审核时间');
            $table->datetimes();

            // 一个人只有一条实名记录（重新提交是覆盖，不是新增）
            $table->unique('user_id');
            $table->index('id_card_hash');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_real_name');
    }
};
