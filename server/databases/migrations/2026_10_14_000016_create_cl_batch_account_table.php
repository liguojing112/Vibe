<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 后台批量开号批次（docs/04 第 7 节）。
 *
 * 这张表**只记批次本身**，不存账号也不存密码：
 * - 生成出来的账号就是普通的 `cl_user` 行（`source = 2`、带 `batch_no`），
 *   批次内有哪些账号靠 `cl_user.batch_no` 反查；
 * - **明文密码一个字节都不落库**（docs/04 第 7 节）：生成时随响应返回一次，
 *   由前端当场下载，服务端只有 `cl_user.password` 里的 bcrypt。
 *   `exported` 就是「这批明文有没有被导出过」，对应批次列表里的「是否已导出」。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_batch_account', static function (Blueprint $table): void {
            $table->comment('后台批量开号批次');
            $table->bigIncrements('id')->comment('主键');
            $table->string('batch_no', 32)->unique()->comment('批次号');
            $table->tinyInteger('gender')->comment('1 男 2 女');
            $table->integer('count')->comment('生成数量');
            $table->string('account_prefix', 8)->comment('账号前缀');
            $table->bigInteger('operator_id')->nullable()->comment('操作的后台账号');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->tinyInteger('exported')->default(0)->comment('明文密码是否已导出');
            $table->datetimes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_batch_account');
    }
};
