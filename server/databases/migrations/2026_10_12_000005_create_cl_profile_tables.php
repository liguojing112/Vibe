<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration
{
    /**
     * 资料相关的两张表（docs/04 第 1 节）。
     */
    public function up(): void
    {
        Schema::create('cl_user_photo', function (Blueprint $table) {
            $table->comment('用户相册（docs/04 第 1 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('用户 ID');
            $table->string('url', 255)->comment('图片地址');
            $table->integer('sort')->default(0)->comment('排序，越小越靠前');
            $table->tinyInteger('audit_status')->default(0)->comment('0 待审 1 通过 2 驳回');
            $table->string('reject_reason', 255)->nullable()->comment('驳回原因');
            $table->datetimes();
            $table->index(['user_id', 'sort'], 'cl_user_photo_user_sort_index');
            $table->index(['user_id', 'audit_status'], 'cl_user_photo_user_audit_index');
        });

        Schema::create('cl_profile_audit', function (Blueprint $table) {
            $table->comment('资料修改审核（docs/04 第 1 节）');
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->comment('用户 ID');
            $table->string('field', 16)->comment('avatar / nickname / bio / photo');
            $table->text('old_value')->nullable()->comment('改动前的值（photo 时存 cl_user_photo.id 列表）');
            $table->text('new_value')->comment('改动后的值（photo 时存 cl_user_photo.id 列表）');
            $table->tinyInteger('status')->default(0)->comment('0 待审 1 通过 2 驳回');
            $table->string('reject_reason', 255)->nullable()->comment('驳回原因');
            $table->bigInteger('auditor_id')->nullable()->comment('后台管理员 ID');
            $table->dateTime('audited_at')->nullable()->comment('审核时间');
            $table->datetimes();
            $table->index(['user_id', 'field', 'status'], 'cl_profile_audit_user_field_status_index');
            $table->index(['status', 'created_at'], 'cl_profile_audit_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_profile_audit');
        Schema::dropIfExists('cl_user_photo');
    }
};
