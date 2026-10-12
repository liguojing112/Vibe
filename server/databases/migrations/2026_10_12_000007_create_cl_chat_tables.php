<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;

return new class extends Migration
{
    /**
     * 聊天三张表（docs/04 第 3 节）。
     */
    public function up(): void
    {
        Schema::create('cl_conversation', function (Blueprint $table) {
            $table->comment('会话（docs/04 第 3 节）：一对用户一条，按 user_low_id < user_high_id 存');
            $table->bigIncrements('id');
            $table->bigInteger('user_low_id')->comment('小的那个用户 id');
            $table->bigInteger('user_high_id')->comment('大的那个用户 id');
            $table->bigInteger('male_id')->comment('男方（冗余，方便计费查询）');
            $table->bigInteger('female_id')->comment('女方（冗余）');
            $table->integer('male_sent_count')->default(0)->comment('男方发给女方的普通消息总数（06 第 3.2 节第 2 步）');
            $table->bigInteger('last_message_id')->nullable()->comment('最后一条消息 id');
            $table->dateTime('last_message_at')->nullable()->comment('最后一条消息时间');
            $table->integer('low_unread')->default(0)->comment('user_low_id 的未读数');
            $table->integer('high_unread')->default(0)->comment('user_high_id 的未读数');
            $table->dateTime('low_deleted_at')->nullable()->comment('user_low_id 删除会话的时间（之后的新消息让会话重新出现）');
            $table->dateTime('high_deleted_at')->nullable()->comment('user_high_id 删除会话的时间');
            $table->datetimes();
            $table->unique(['user_low_id', 'user_high_id'], 'cl_conversation_pair_unique');
            $table->index(['male_id', 'female_id'], 'cl_conversation_male_female_index');
            $table->index(['last_message_at'], 'cl_conversation_last_message_index');
        });

        // 「按 user_low_id < user_high_id 存」这条约定只靠唯一索引是挡不住的：
        // (3,7) 和 (7,3) 是两行，反着写就会同一对人出现两条会话、未读数分家。用 CHECK 兜住。
        Db::statement('ALTER TABLE `cl_conversation` ADD CONSTRAINT `chk_cl_conversation_pair` CHECK (`user_low_id` < `user_high_id`)');

        Schema::create('cl_message', function (Blueprint $table) {
            $table->comment('消息（docs/04 第 3 节）：服务端只做转发、计费与监管留存，App 展示以本地库为准');
            $table->bigIncrements('id');
            $table->bigInteger('conversation_id')->comment('所属会话');
            $table->bigInteger('sender_id')->comment('发送者');
            $table->bigInteger('receiver_id')->comment('接收者');
            $table->string('client_msg_id', 64)->comment('客户端消息 id（幂等用）');
            $table->tinyInteger('type')->comment('1 文字 2 图片 3 礼物 4 系统提示');
            $table->text('content')->comment('文字内容 / 图片 URL / 礼物 JSON');
            $table->integer('price_coins')->default(0)->comment('本条实际扣的金币');
            $table->string('free_reason', 16)->nullable()->comment('MUTUAL_FOLLOW / PAIR_FREE / VIP_FREE / FEMALE_SEND / GIFT');
            $table->tinyInteger('receiver_level')->nullable()->comment('发送时女方等级');
            $table->integer('share_rate')->nullable()->comment('实际使用的分成（万分比，已含未实名系数）');
            $table->integer('receiver_points')->default(0)->comment('女方本条获得积分');
            $table->tinyInteger('sensitive_hit')->default(0)->comment('1 = 命中敏感词');
            $table->tinyInteger('review_status')->default(0)->comment('0 无需审核 1 待审核 2 审核正常 3 审核违规');
            $table->dateTime('read_at')->nullable()->comment('接收者读过的时间');
            $table->datetimes();
            $table->unique(['sender_id', 'client_msg_id'], 'cl_message_client_unique');
            $table->index(['conversation_id', 'id'], 'cl_message_conversation_index');
            $table->index('review_status', 'cl_message_review_index');
            $table->index(['sender_id', 'created_at'], 'cl_message_sender_index');
        });

        Schema::create('cl_sensitive_word', function (Blueprint $table) {
            $table->comment('敏感词库（docs/04 第 3 节）：命中只标记不拦截');
            $table->bigIncrements('id');
            $table->string('word', 64)->unique()->comment('敏感词');
            $table->tinyInteger('enabled')->default(1)->comment('1 启用 0 停用');
            $table->bigInteger('creator_id')->nullable()->comment('谁加的（后台管理员 id）');
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cl_sensitive_word');
        Schema::dropIfExists('cl_message');
        Schema::dropIfExists('cl_conversation');
    }
};
