<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Ai;

use Hyperf\DbConnection\Db;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 菜单迁移 `2026_11_09_000030_add_bot_chat_menu`。
 *
 * 全新安装时菜单由种子负责，迁移只服务「已经上线的库」——
 * 所以这条路径必须单独验证：**插入分支跑不通，后台就根本看不到这个页面**，
 * 而 `migrate` 又是不报错地跑完的（表里本来没有 `cl:content` 就直接 return）。
 *
 * @internal
 * @coversNothing
 */
final class BotChatMenuMigrationTest extends TestCase
{
    use RunTestsInCoroutine;

    private const MIGRATION = BASE_PATH . '/databases/migrations/2026_11_09_000030_add_bot_chat_menu.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->seedParentMenu();
        $this->seedRole();
    }

    protected function tearDown(): void
    {
        $this->clear();
        parent::tearDown();
    }

    public function testMigrationAddsPageButtonsAndGrantsThemToTheReviewerRole(): void
    {
        $this->runMigration('up');

        $parentId = (int) Db::table('menu')->where('name', 'cl:content')->value('id');
        $page = Db::table('menu')->where('name', 'cl:content:bot')->first();
        self::assertNotNull($page, '页面菜单必须插进来');
        self::assertSame($parentId, (int) $page->parent_id);
        self::assertSame('/cl/content/bot-chat', (string) $page->path);
        self::assertSame('cl/views/content/botChat/index', (string) $page->component);

        $meta = json_decode((string) $page->meta, true);
        self::assertSame('AI 互聊', $meta['title'] ?? null);
        self::assertSame('M', $meta['type'] ?? null);

        foreach (['cl:content:bot:list', 'cl:content:bot:edit'] as $code) {
            $button = Db::table('menu')->where('name', $code)->first();
            self::assertNotNull($button, $code . ' 必须插进来');
            self::assertSame((int) $page->id, (int) $button->parent_id);
            self::assertSame('B', json_decode((string) $button->meta, true)['type'] ?? null);
        }

        $roleId = (int) Db::table('role')->where('code', 'ClShenhe')->value('id');
        $granted = Db::table('role_belongs_menu')->where('role_id', $roleId)->count();
        self::assertSame(4, $granted, '父分组 + 页面 + 2 个按钮都要给审核员');
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->runMigration('up');
        $this->runMigration('up');

        self::assertSame(1, Db::table('menu')->where('name', 'cl:content:bot')->count());
        self::assertSame(1, Db::table('menu')->where('name', 'cl:content:bot:list')->count());

        $roleId = (int) Db::table('role')->where('code', 'ClShenhe')->value('id');
        self::assertSame(4, Db::table('role_belongs_menu')->where('role_id', $roleId)->count());
    }

    public function testDownRemovesEverythingItAdded(): void
    {
        $this->runMigration('up');
        $this->runMigration('down');

        self::assertSame(0, Db::table('menu')->where('name', 'like', 'cl:content:bot%')->count());
        self::assertNotNull(Db::table('menu')->where('name', 'cl:content')->first(), '父分组不能删');

        // 父分组 `cl:content` 是别的页面也在用的，回滚不能把它一起摘掉
        $roleId = (int) Db::table('role')->where('code', 'ClShenhe')->value('id');
        $grantedMenuIds = Db::table('role_belongs_menu')->where('role_id', $roleId)->pluck('menu_id')->map(static fn ($id): int => (int) $id)->all();
        self::assertSame([(int) Db::table('menu')->where('name', 'cl:content')->value('id')], $grantedMenuIds);
    }

    /** 迁移文件返回的是匿名类，每次 require 都拿到一个新实例。 */
    private function runMigration(string $method): void
    {
        $migration = require self::MIGRATION;
        $migration->{$method}();
    }

    private function seedParentMenu(): void
    {
        Db::table('menu')->insert([
            'parent_id' => 0,
            'name' => 'cl:content',
            'path' => '/cl/content',
            'component' => '',
            'redirect' => '',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'remark' => '',
            'meta' => (string) json_encode(['title' => '内容管理', 'type' => 'M']),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedRole(): void
    {
        Db::table('role')->insert([
            'name' => '审核员',
            'code' => 'ClShenhe',
            'status' => 1,
            'sort' => 0,
            'created_by' => 0,
            'updated_by' => 0,
            'remark' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function clear(): void
    {
        $ids = Db::table('menu')->where('name', 'like', 'cl:content%')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($ids !== []) {
            Db::table('role_belongs_menu')->whereIn('menu_id', $ids)->delete();
            // 先删子、再删父（都是同一个前缀，按 id 倒序删就够了）
            rsort($ids);
            Db::table('menu')->whereIn('id', $ids)->delete();
        }

        Db::table('role')->where('code', 'ClShenhe')->delete();
    }
}
