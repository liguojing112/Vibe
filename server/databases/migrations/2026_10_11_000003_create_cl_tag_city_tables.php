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
        Schema::create('cl_tag', function (Blueprint $table) {
            $table->comment('兴趣标签库（docs/04 第 1 节）');
            $table->bigIncrements('id');
            $table->string('name', 16)->comment('标签名');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('enabled')->default(1)->comment('1 启用 0 停用');
            $table->datetimes();
        });

        Schema::create('cl_city', function (Blueprint $table) {
            $table->comment('热门城市（docs/04 第 1 节，用于筛选 Chip）');
            $table->bigIncrements('id');
            $table->string('name', 32)->comment('城市名');
            $table->integer('sort')->default(0)->comment('排序');
            $table->tinyInteger('enabled')->default(1)->comment('1 启用 0 停用');
            $table->datetimes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cl_city');
        Schema::dropIfExists('cl_tag');
    }
};
