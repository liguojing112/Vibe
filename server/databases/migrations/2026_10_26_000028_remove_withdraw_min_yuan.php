<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 取消「最低提现金额」配置（客户 2026-10-26）。
 *
 * 提现只按 `withdraw.tiers` 档位校验；低于某个金额就提不了的限制去掉。
 * 已上线的库靠这条迁移删除配置项，全新安装时种子也不会再插入。
 */
return new class extends Migration {
    private const KEY = 'withdraw.min_yuan';

    public function up(): void
    {
        Db::table('cl_config')->where('key', self::KEY)->delete();
    }

    public function down(): void
    {
        Db::table('cl_config')->updateOrInsert(
            ['key' => self::KEY],
            [
                'value' => '50',
                'value_type' => 'int',
                'group' => 'withdraw',
                'label' => '最低提现金额（元）',
                'remark' => '',
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ],
        );
    }
};
