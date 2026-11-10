<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 补一把 `2026_11_10_000035` 没覆盖到的尾巴。
 *
 * 那次发版的顺序是「先跑迁移、后重启进程」：迁移跑完、进程还没换上新代码的
 * 那几十秒里，旧的发送逻辑又写进了几条 `AI 互聊收入`。
 *
 * 幂等：没有旧文案要改时什么也不做。
 */
return new class extends Migration {
    private const REF_TYPE = 'bot_message';

    public function up(): void
    {
        $this->rename('AI 互聊收入', '私信收入');
    }

    public function down(): void
    {
        // 不回滚：把已展示给用户的文案再改回「AI 互聊收入」没有意义
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
