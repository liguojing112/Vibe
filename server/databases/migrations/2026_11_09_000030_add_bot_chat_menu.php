<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 「内容管理 → AI 互聊」菜单 + 两个按钮权限。
 *
 * 全新安装时 `migrate` 早于 `db:seed`，menu 表还是空的，本迁移是空操作，
 * 菜单由 `zz_cl_menu_seeder_20261014.php` 负责。已经上线的库靠这条迁移补菜单。
 *
 * 同时也把这两个权限补给现有的「审核员」角色：它本来就管 AI 代聊，
 * 互聊属于同一类运营内容。新建角色或自定义角色不受影响。
 */
return new class extends Migration {
    private const PAGE = 'cl:content:bot';

    private const PARENT = 'cl:content';

    private const BUTTONS = [
        'cl:content:bot:list' => '查看 AI 互聊',
        'cl:content:bot:edit' => '配置互聊规则',
    ];

    public function up(): void
    {
        $parentId = (int) (Db::table('menu')->where('name', self::PARENT)->value('id') ?? 0);
        if ($parentId === 0) {
            return;
        }

        $pageId = (int) (Db::table('menu')->where('name', self::PAGE)->value('id') ?? 0);
        if ($pageId === 0) {
            $pageId = (int) Db::table('menu')->insertGetId($this->menuRow($parentId, [
                'name' => self::PAGE,
                'path' => '/cl/content/bot-chat',
                'component' => 'cl/views/content/botChat/index',
                'meta' => $this->meta('AI 互聊', 'ri:user-voice-line', 'M'),
            ]));
        }

        $buttonIds = [$pageId];
        foreach (self::BUTTONS as $name => $title) {
            $exists = (int) (Db::table('menu')->where('name', $name)->value('id') ?? 0);
            if ($exists === 0) {
                $exists = (int) Db::table('menu')->insertGetId($this->menuRow($pageId, [
                    'name' => $name,
                    'meta' => $this->meta($title, '', 'B'),
                ]));
            }
            $buttonIds[] = $exists;
        }

        $this->grantToRole('ClShenhe', array_merge([$parentId], $buttonIds));
    }

    public function down(): void
    {
        $pageId = (int) (Db::table('menu')->where('name', self::PAGE)->value('id') ?? 0);
        if ($pageId === 0) {
            return;
        }

        // 只回收这个页面自己的授权；`cl:content` 父分组是共用的（朋友圈、AI 代聊也挂在下面），
        // 回滚时不能把它从角色上摘掉，否则会把人家本来有的菜单一起弄没。
        Db::table('role_belongs_menu')->whereIn('menu_id', array_values(array_filter(array_merge(
            [$pageId],
            array_map(
                static fn (string $name): int => (int) (Db::table('menu')->where('name', $name)->value('id') ?? 0),
                array_keys(self::BUTTONS),
            ),
        ))))->delete();

        Db::table('menu')->where('parent_id', $pageId)->delete();
        Db::table('menu')->where('id', $pageId)->delete();
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function menuRow(int $parentId, array $extra): array
    {
        return $extra + [
            'parent_id' => $parentId,
            'path' => '',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'remark' => '',
            'meta' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function meta(string $title, string $icon, string $type): string
    {
        return (string) json_encode([
            'title' => $title,
            'icon' => $icon,
            'type' => $type,
            'hidden' => 0,
            'componentPath' => 'modules/',
            'componentSuffix' => '.vue',
            'breadcrumbEnable' => 1,
            'copyright' => 1,
            'cache' => 1,
            'affix' => 0,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 把菜单挂到指定角色上。角色不存在（客户自己改过角色名）就跳过 —— 宁可少给权限，
     * 也不能在迁移里新建角色。
     *
     * @param array<int,int> $menuIds
     */
    private function grantToRole(string $code, array $menuIds): void
    {
        $roleId = (int) (Db::table('role')->where('code', $code)->value('id') ?? 0);
        if ($roleId === 0) {
            return;
        }

        $existing = Db::table('role_belongs_menu')
            ->where('role_id', $roleId)
            ->whereIn('menu_id', $menuIds)
            ->pluck('menu_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $rows = [];
        foreach (array_unique($menuIds) as $menuId) {
            if ($menuId === 0 || in_array($menuId, $existing, true)) {
                continue;
            }
            $rows[] = [
                'role_id' => $roleId,
                'menu_id' => $menuId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        if ($rows !== []) {
            Db::table('role_belongs_menu')->insert($rows);
        }
    }
};
