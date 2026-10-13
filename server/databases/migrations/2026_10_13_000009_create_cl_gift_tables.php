<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

/**
 * 礼物与赠送记录（docs/04 第 5 节）。
 *
 * `cl_gift_record` 里把**送出那一刻的礼物名和价格**都存下来：
 * 后台以后调价或下架礼物，历史记录也必须还原成当时的样子（对账要能对上）。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cl_gift', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->string('name', 16)->comment('礼物名');
            $table->string('icon', 255)->comment('图标（emoji 或图片 URL）');
            $table->integer('price_coins')->comment('价格（金币）');
            $table->integer('sort')->default(0)->comment('排序，越小越前');
            $table->tinyInteger('enabled')->default(1)->comment('1 上架 0 下架');
            $table->datetimes();

            $table->index(['enabled', 'sort']);
        });

        Schema::create('cl_gift_record', static function (Blueprint $table): void {
            $table->bigIncrements('id')->comment('主键');
            $table->string('client_order_id', 64)->comment('客户端订单号（幂等）');
            $table->bigInteger('sender_id')->comment('送礼人（男）');
            $table->bigInteger('receiver_id')->comment('收礼人（女）');
            $table->bigInteger('gift_id')->comment('礼物 id');
            $table->string('gift_name', 16)->comment('送出时的礼物名');
            $table->integer('price_coins')->comment('送出时的价格');
            $table->integer('share_rate')->default(0)->comment('实际分成（万分比，含未实名系数）');
            $table->integer('receiver_points')->default(0)->comment('女方拿到的积分');
            $table->bigInteger('message_id')->nullable()->comment('会话里那条礼物消息');
            $table->datetimes();

            // 幂等：同一个人同一个订单号只能记一次
            $table->unique(['sender_id', 'client_order_id']);
            $table->index('receiver_id');
            $table->index('gift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_gift_record');
        Schema::dropIfExists('cl_gift');
    }
};
