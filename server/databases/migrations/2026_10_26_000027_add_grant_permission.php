<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 用户列表「加款」的权限节点，并授给客服角色。
 *
 * 全新安装时 `migrate` 早于 `db:seed`，menu/role 表还是空的，本迁移是空操作；
 * 菜单和角色绑定由 `zz_cl_menu_seeder_20261014.php` 负责。已上线的库靠这条迁移补。
 */
return new class extends Migration {
    private const MENU_NAME = 'cl:finance:grant';

    public function up(): void
    {
        $parentId = (int) (Db::table('menu')->where('name', 'cl:finance')->value('id') ?? 0);
        if ($parentId === 0) {
            return;
        }

        $menuId = (int) (Db::table('menu')->where('name', self::MENU_NAME)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) Db::table('menu')->insertGetId([
                'parent_id' => $parentId,
                'name' => self::MENU_NAME,
                'path' => '',
                'component' => '',
                'redirect' => '',
                'status' => 1,
                'sort' => 0,
                'created_by' => 0,
                'updated_by' => 0,
                'remark' => '',
                'meta' => json_encode(['title' => '加款', 'type' => 'B'], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $roleId = (int) (Db::table('role')->where('code', 'ClKefu')->value('id') ?? 0);
        if ($roleId === 0) {
            return;
        }

        $exists = Db::table('role_belongs_menu')
            ->where('role_id', $roleId)
            ->where('menu_id', $menuId)
            ->exists();
        if (! $exists) {
            Db::table('role_belongs_menu')->insert([
                'role_id' => $roleId,
                'menu_id' => $menuId,
            ]);
        }
    }

    public function down(): void
    {
        $menuId = (int) (Db::table('menu')->where('name', self::MENU_NAME)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        Db::table('role_belongs_menu')->where('menu_id', $menuId)->delete();
        Db::table('menu')->where('id', $menuId)->delete();
    }
};
