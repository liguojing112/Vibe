<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 「运营配置 → 在线支付」菜单。
 *
 * 全新安装时 `migrate` 早于 `db:seed`，menu 表还是空的，本迁移是空操作；
 * 菜单由 `zz_cl_menu_seeder_20261014.php` 负责。已经上线的库靠这条迁移补菜单。
 *
 * 权限码沿用 `cl:operation:recharge`（和「充值档位与客服」同一页权限），
 * 所以不用改角色种子；超管本来就有全部权限。
 */
return new class extends Migration {
    private const NAME = 'cl:operation:pay';

    public function up(): void
    {
        $parentId = (int) (Db::table('menu')->where('name', 'cl:operation')->value('id') ?? 0);
        if ($parentId === 0) {
            return;
        }

        $exists = Db::table('menu')->where('name', self::NAME)->exists();
        if ($exists) {
            return;
        }

        Db::table('menu')->insert([
            'parent_id' => $parentId,
            'name' => self::NAME,
            'path' => '/cl/operation/pay',
            'component' => 'cl/views/operation/pay/index',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'remark' => '',
            'meta' => json_encode([
                'title' => '在线支付',
                'icon' => 'ri:alipay-line',
                'type' => 'M',
                'hidden' => 0,
                'componentPath' => 'modules/',
                'componentSuffix' => '.vue',
                'breadcrumbEnable' => 1,
                'copyright' => 1,
                'cache' => 1,
                'affix' => 0,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function down(): void
    {
        Db::table('menu')->where('name', self::NAME)->delete();
    }
};
