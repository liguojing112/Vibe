<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 隐藏后台用不上的 MineAdmin 默认模块（交付时只给客户业务功能）。
 *
 * 超级管理员的菜单是按 `menu.status = 1` 过滤的（`PermissionController::menus()`），
 * 所以置 0 即可从侧边栏消失；**数据不删**，以后要恢复把 status 改回 1 就行。
 *
 * 隐藏的三类：
 * - `permission:menu*`       菜单管理 —— 不给客户自己加菜单/页面的口子；
 * - `permission:department*` 部门管理（含岗位、部门领导）—— 本项目不用部门；
 * - `dataCenter*`            附件管理 —— 后台用自己的上传流程，这个页面没用。
 *
 * **保留**：权限管理下的「用户管理」「角色管理」（要管那 4 个后台账号）、日志管理、
 * 以及「畅撩交友」下全部业务菜单。
 *
 * 注意：全新安装时 `migrate` 早于 `db:seed`，这时 menu 表还是空的，本迁移是空操作；
 * 新建的菜单由 `zz_cl_menu_seeder_20261014.php` 里的同名逻辑负责隐藏。
 */
return new class extends Migration
{
    /** 按名字前缀匹配要隐藏的菜单（含子菜单）。 */
    private const HIDDEN_PATTERNS = [
        'permission:menu%',
        'permission:department%',
        'permission:position%',
        'permission:leader%',
        'dataCenter%',
    ];

    public function up(): void
    {
        foreach (self::HIDDEN_PATTERNS as $pattern) {
            Db::table('menu')->where('name', 'like', $pattern)->update(['status' => 0]);
        }
    }

    public function down(): void
    {
        foreach (self::HIDDEN_PATTERNS as $pattern) {
            Db::table('menu')->where('name', 'like', $pattern)->update(['status' => 1]);
        }
    }
};
