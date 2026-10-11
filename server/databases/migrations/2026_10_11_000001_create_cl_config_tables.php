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
        Schema::create('cl_config', function (Blueprint $table) {
            $table->comment('运营配置（后台可调的键值对，见 docs/06 第 2 节）');
            $table->bigIncrements('id');
            $table->string('key', 64)->unique()->comment('配置键');
            $table->text('value')->nullable()->comment('配置值（统一按字符串存，读取时按 value_type 转换）');
            $table->string('value_type', 8)->default('string')->comment('值类型：int / bool / json / string');
            $table->string('group', 16)->default('billing')->comment('配置分组：billing/share/chat/vip/withdraw/recharge/service/feature/compliance/agreement');
            $table->string('label', 64)->default('')->comment('后台显示名');
            $table->string('remark', 255)->default('')->comment('说明');
            $table->datetimes();
            $table->index('group', 'cl_config_group_index');
        });

        Schema::create('cl_level_config', function (Blueprint $table) {
            $table->comment('女用户等级配置（5 行，见 docs/06 第 2.3 节）');
            $table->bigIncrements('id');
            $table->unsignedTinyInteger('level')->unique()->comment('等级 1~5');
            $table->string('name', 16)->comment('等级名称');
            $table->bigInteger('threshold_points')->default(0)->comment('升级门槛：累计收益积分');
            $table->integer('message_price')->default(0)->comment('私信单价（金币/条）');
            $table->integer('message_share_rate')->default(0)->comment('私信分成（万分比，3000 = 30%）');
            $table->integer('voice_price')->default(0)->comment('语音单价（金币/分钟，仅展示）');
            $table->integer('video_price')->default(0)->comment('视频单价（金币/分钟，仅展示）');
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cl_level_config');
        Schema::dropIfExists('cl_config');
    }
};
