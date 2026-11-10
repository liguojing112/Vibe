<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 收益明细里的文案：`AI 互聊收入` → `私信收入`（客户 2026-11-10）。
 *
 * 这条流水会在**女号的 App 收益明细**里展示，写「AI 互聊收入」等于告诉她这是机器人，
 * 和真实付费私信统一成同一个文案。
 *
 * 只改机器人消息产生的那批（`ref_type = bot_message`），真实付费的流水不动。
 */
return new class extends Migration {
    private const REF_TYPE = 'bot_message';

    public function up(): void
    {
        $this->rename('AI 互聊收入', '私信收入');
    }

    public function down(): void
    {
        $this->rename('私信收入', 'AI 互聊收入');
    }

    private function rename(string $from, string $to): void
    {
        Db::table('cl_point_log')
            ->where('ref_type', self::REF_TYPE)
            ->where('title', 'like', $from . '%')
            ->update([
                'title' => Db::raw(sprintf("REPLACE(title, '%s', '%s')", $from, $to)),
            ]);
    }
};
