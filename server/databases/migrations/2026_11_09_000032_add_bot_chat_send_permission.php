<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 「立即发送」按钮权限（客户 2026-11-10）。
 *
 * 和「配置规则」分开：客服/运营点按钮发消息，但不一定能改规则本身。
 * 已经上线的库靠这条迁移补；全新安装由菜单种子负责。
 */
return new class extends Migration {
    private const CODE = 'cl:content:bot:send';

    private const PAGE = 'cl:content:bot';

    public function up(): void
    {
        $pageId = (int) (Db::table('menu')->where('name', self::PAGE)->value('id') ?? 0);
        if ($pageId === 0) {
            return;
        }

        $exists = (int) (Db::table('menu')->where('name', self::CODE)->value('id') ?? 0);
        if ($exists === 0) {
            $exists = (int) Db::table('menu')->insertGetId([
                'parent_id' => $pageId,
                'name' => self::CODE,
                'path' => '',
                'component' => '',
                'redirect' => '',
                'status' => 1,
                'sort' => 0,
                'created_by' => 0,
                'updated_by' => 0,
                'remark' => '',
                'meta' => (string) json_encode(['title' => '点击发送消息', 'type' => 'B'], JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->grantToRole('ClShenhe', $exists);
    }

    public function down(): void
    {
        $id = (int) (Db::table('menu')->where('name', self::CODE)->value('id') ?? 0);
        if ($id === 0) {
            return;
        }

        Db::table('role_belongs_menu')->where('menu_id', $id)->delete();
        Db::table('menu')->where('id', $id)->delete();
    }

    private function grantToRole(string $code, int $menuId): void
    {
        $roleId = (int) (Db::table('role')->where('code', $code)->value('id') ?? 0);
        if ($roleId === 0) {
            return;
        }

        $exists = Db::table('role_belongs_menu')
            ->where('role_id', $roleId)
            ->where('menu_id', $menuId)
            ->exists();
        if ($exists) {
            return;
        }

        Db::table('role_belongs_menu')->insert([
            'role_id' => $roleId,
            'menu_id' => $menuId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
};
