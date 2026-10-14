<?php

declare(strict_types=1);

use App\Model\Permission\Menu;
use App\Model\Permission\Meta;
use App\Model\Permission\Role;
use App\Model\Permission\User;
use Hyperf\Database\Seeders\Seeder;

/**
 * 「畅撩交友」后台菜单、权限码与三个业务角色（docs/07 第 1、4 节）。
 *
 * ⚠️ **文件名必须以 `zz_` 开头**，别按 docs/07 写的 `cl_menu_seeder.php` 改回去。
 * Hyperf 的 `db:seed` 是**按文件名排序**跑的（`Seed::getSeederName`），而：
 *   - `menu_seeder_20240926.php` 里有 `Menu::truncate()`，
 *   - `user_seeder_20240926.php` 里有 `Role::truncate()` 和 `User::truncate()`。
 * 名字排在它们前面的话，这里建好的菜单和角色会被它们清掉 —— `z` 比 `m`/`u` 大，
 * 所以放在最后跑（上游自己的「后加菜单」文件 `menu_update_*` 也是靠这个顺序）。
 *
 * 这个文件**不 truncate 任何东西**：基础菜单和 admin 账号由上面两个种子负责，这里只做追加。
 * 所以「幂等」只在**整跑 `db:seed`** 的时候成立（前面那两个种子会先清表，这里再重建）；
 * 单独跑这个文件两次会把菜单和角色插两遍。
 */
class ZzClMenuSeeder20261014 extends Seeder
{
    public const BASE_DATA = [
        'name' => '',
        'path' => '',
        'component' => '',
        'redirect' => '',
        'created_by' => 0,
        'updated_by' => 0,
        'remark' => '',
    ];

    /** 三个业务角色的账号密码（和 admin 一样，方便本地走查）。 */
    public const PASSWORD = '123456';

    /**
     * 角色 → 权限码（`*` 结尾表示前缀匹配）。
     *
     * 照 docs/07 第 4 节那张表；表里写 `cl:chat:*` / `cl:report:*` / `cl:operation:*`
     * 的地方在这里展开成具体码。**父级分组不用写**，下面会自动补上（不补的话
     * `CurrentUser::filterCurrentUser()` 建不出树，叶子会散在顶层）。
     */
    public const ROLES = [
        'kefu' => [
            'name' => '客服',
            'permissions' => [
                'cl:dashboard',
                'cl:user:male', 'cl:user:female',
                'cl:user:list', 'cl:user:detail', 'cl:user:update', 'cl:user:reset-password',
                'cl:user:ban', 'cl:user:vip-grant',
                'cl:report:*',
                'cl:finance:recharge', 'cl:finance:recharge-list',
                'cl:finance:coin-log', 'cl:finance:point-log',
                'cl:operation:notice',
            ],
        ],
        'caiwu' => [
            'name' => '财务',
            'permissions' => [
                'cl:dashboard',
                'cl:user:male', 'cl:user:female',
                'cl:user:list', 'cl:user:detail',
                'cl:finance:recharge-list',
                'cl:finance:coin-log', 'cl:finance:point-log',
                'cl:withdraw:*',
                'cl:finance:income',
            ],
        ],
        'shenhe' => [
            'name' => '审核员',
            'permissions' => [
                'cl:dashboard',
                'cl:user:male', 'cl:user:female',
                'cl:user:list', 'cl:user:detail', 'cl:user:ban',
                'cl:audit:profile', 'cl:audit:realname',
                'cl:chat:*',
                'cl:report:*',
            ],
        ],
    ];

    public function run(): void
    {
        $this->create($this->data());
        $this->createRoles();
    }

    public function data(): array
    {
        return [
            [
                'name' => 'cl',
                'path' => '/cl',
                'meta' => $this->group('畅撩交友', 'ri:heart-3-line', 90),
                'children' => [
                    [
                        'name' => 'cl:dashboard',
                        'path' => '/cl/dashboard',
                        'component' => 'cl/views/dashboard/index',
                        'meta' => $this->page('数据看板', 'ri:dashboard-3-line'),
                    ],

                    [
                        'name' => 'cl:user',
                        'path' => '/cl/user',
                        'meta' => $this->group('用户管理', 'ri:user-3-line', 1),
                        'children' => [
                            [
                                'name' => 'cl:user:male',
                                'path' => '/cl/user/male',
                                'component' => 'cl/views/user/male/index',
                                'meta' => $this->page('男用户', 'ri:men-line'),
                            ],
                            [
                                'name' => 'cl:user:female',
                                'path' => '/cl/user/female',
                                'component' => 'cl/views/user/female/index',
                                'meta' => $this->page('女用户', 'ri:women-line'),
                            ],
                            [
                                'name' => 'cl:user:batch',
                                'path' => '/cl/user/batch',
                                'component' => 'cl/views/user/batch/index',
                                'meta' => $this->page('批量开号', 'ri:file-add-line'),
                            ],
                            $this->button('cl:user:list', '用户列表'),
                            $this->button('cl:user:detail', '用户详情'),
                            $this->button('cl:user:update', '修改用户资料'),
                            $this->button('cl:user:reset-password', '重置密码'),
                            $this->button('cl:user:ban', '封禁/解封'),
                            $this->button('cl:user:level', '调整等级'),
                            $this->button('cl:user:gender', '修改性别'),
                            $this->button('cl:user:vip-grant', '赠送会员'),
                        ],
                    ],

                    [
                        'name' => 'cl:audit',
                        'path' => '/cl/audit',
                        'meta' => $this->group('审核中心', 'ri:shield-check-line', 2),
                        'children' => [
                            [
                                'name' => 'cl:audit:profile',
                                'path' => '/cl/audit/profile',
                                'component' => 'cl/views/audit/profile/index',
                                'meta' => $this->page('资料审核', 'ri:user-follow-line'),
                            ],
                            [
                                'name' => 'cl:audit:realname',
                                'path' => '/cl/audit/realname',
                                'component' => 'cl/views/audit/realname/index',
                                'meta' => $this->page('实名审核', 'ri:id-card-line'),
                            ],
                            $this->button('cl:audit:realname-plain', '查看身份证明文'),
                        ],
                    ],

                    [
                        'name' => 'cl:chat',
                        'path' => '/cl/chat',
                        'meta' => $this->group('聊天管理', 'ri:chat-3-line', 3),
                        'children' => [
                            [
                                'name' => 'cl:chat:conversation',
                                'path' => '/cl/chat/conversation',
                                'component' => 'cl/views/chat/conversation/index',
                                'meta' => $this->page('会话记录', 'ri:chat-history-line'),
                            ],
                            [
                                'name' => 'cl:chat:pending',
                                'path' => '/cl/chat/pending',
                                'component' => 'cl/views/chat/pending/index',
                                'meta' => $this->page('待审消息', 'ri:chat-check-line'),
                            ],
                            [
                                'name' => 'cl:chat:sensitive',
                                'path' => '/cl/chat/sensitive',
                                'component' => 'cl/views/chat/sensitive/index',
                                'meta' => $this->page('敏感词库', 'ri:forbid-2-line'),
                            ],
                            [
                                'name' => 'cl:report:list',
                                'path' => '/cl/chat/report',
                                'component' => 'cl/views/chat/report/index',
                                'meta' => $this->page('举报处理', 'ri:flag-line'),
                            ],
                            $this->button('cl:report:handle', '处理举报'),
                        ],
                    ],

                    [
                        'name' => 'cl:finance',
                        'path' => '/cl/finance',
                        'meta' => $this->group('资金管理', 'ri:money-cny-circle-line', 4),
                        'children' => [
                            [
                                'name' => 'cl:finance:recharge',
                                'path' => '/cl/finance/recharge',
                                'component' => 'cl/views/finance/recharge/index',
                                'meta' => $this->page('后台充值', 'ri:add-circle-line'),
                            ],
                            [
                                'name' => 'cl:finance:recharge-list',
                                'path' => '/cl/finance/recharge-list',
                                'component' => 'cl/views/finance/rechargeList/index',
                                'meta' => $this->page('充值记录', 'ri:file-list-3-line'),
                            ],
                            [
                                'name' => 'cl:finance:coin-log',
                                'path' => '/cl/finance/coin-log',
                                'component' => 'cl/views/finance/coinLog/index',
                                'meta' => $this->page('金币流水', 'ri:coins-line'),
                            ],
                            [
                                'name' => 'cl:finance:point-log',
                                'path' => '/cl/finance/point-log',
                                'component' => 'cl/views/finance/pointLog/index',
                                'meta' => $this->page('积分流水', 'ri:star-smile-line'),
                            ],
                            [
                                'name' => 'cl:withdraw:list',
                                'path' => '/cl/finance/withdraw',
                                'component' => 'cl/views/finance/withdraw/index',
                                'meta' => $this->page('提现审核', 'ri:bank-card-line'),
                            ],
                            [
                                'name' => 'cl:finance:income',
                                'path' => '/cl/finance/income',
                                'component' => 'cl/views/finance/income/index',
                                'meta' => $this->page('平台收入', 'ri:line-chart-line'),
                            ],
                            $this->button('cl:withdraw:approve', '通过提现'),
                            $this->button('cl:withdraw:reject', '驳回提现'),
                            $this->button('cl:withdraw:paid', '标记已打款'),
                            $this->button('cl:finance:coin-deduct', '扣减金币'),
                            $this->button('cl:finance:point-adjust', '调整积分'),
                        ],
                    ],

                    [
                        'name' => 'cl:operation',
                        'path' => '/cl/operation',
                        'meta' => $this->group('运营配置', 'ri:settings-3-line', 5),
                        'children' => [
                            $this->operation('cl:operation:billing', 'billing', '计费与分成', 'ri:price-tag-3-line'),
                            $this->operation('cl:operation:level', 'level', '等级配置', 'ri:bar-chart-2-line'),
                            $this->operation('cl:operation:gift', 'gift', '礼物管理', 'ri:gift-line'),
                            $this->operation('cl:operation:vip', 'vip', '会员套餐', 'ri:vip-crown-line'),
                            $this->operation('cl:operation:vip-order', 'vipOrder', '会员开通记录', 'ri:file-user-line'),
                            $this->operation('cl:operation:task', 'task', '每日任务', 'ri:calendar-check-line'),
                            $this->operation('cl:operation:recharge', 'recharge', '充值档位与客服', 'ri:customer-service-2-line'),
                            $this->operation('cl:operation:notice', 'notice', '系统通知', 'ri:notification-3-line'),
                            $this->operation('cl:operation:agreement', 'agreement', '协议与声明', 'ri:file-text-line'),
                            $this->operation('cl:operation:tag-city', 'tagCity', '兴趣标签与城市', 'ri:map-pin-line'),
                            $this->operation('cl:operation:feature', 'feature', '功能开关', 'ri:toggle-line'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /** 递归插入菜单树（和 `menu_seeder_20240926.php` 里那份一样，各菜单种子自己带一份）。 */
    public function create(array $data, int $parent_id = 0): void
    {
        foreach ($data as $v) {
            $_v = $v;
            if (isset($v['children'])) {
                unset($_v['children']);
            }
            $_v['parent_id'] = $parent_id;
            $menu = Menu::create(array_merge(self::BASE_DATA, $_v));
            if (isset($v['children']) && count($v['children'])) {
                $this->create($v['children'], $menu->id);
            }
        }
    }

    // ---------- 角色 ----------

    private function createRoles(): void
    {
        /** @var array<string, int> $codeToId */
        $codeToId = [];
        /** @var array<string, string> $parentOf */
        $parentOf = [];
        $this->flatMenus($codeToId, $parentOf);

        foreach (self::ROLES as $username => $role) {
            $entity = Role::query()->create([
                'name' => $role['name'],
                'code' => 'Cl' . ucfirst($username),
                'status' => 1,
                'sort' => 0,
                'created_by' => 0,
                'updated_by' => 0,
                'remark' => '畅撩交友后台角色（种子创建）',
            ]);

            $ids = [];
            foreach ($codeToId as $code => $id) {
                if (! $this->allowed($code, $role['permissions'])) {
                    continue;
                }
                $ids[] = $id;
                // 父级分组必须一起给，否则前端建不出菜单树（叶子会散在顶层）
                for ($parent = $parentOf[$code] ?? ''; $parent !== ''; $parent = $parentOf[$parent] ?? '') {
                    $ids[] = $codeToId[$parent];
                }
            }
            $entity->menus()->sync(array_values(array_unique($ids)));

            $user = User::query()->create([
                'username' => $username,
                'user_type' => '100',
                'nickname' => $role['name'],
                'password' => self::PASSWORD,
                'status' => 1,
                'created_by' => 0,
                'updated_by' => 0,
            ]);
            $user->roles()->sync($entity);
        }
    }

    /**
     * 从库里读回菜单，拍平成 `码 => id` 和 `子码 => 父码`。
     *
     * @param array<string, int> $codeToId
     * @param array<string, string> $parentOf
     */
    private function flatMenus(array &$codeToId, array &$parentOf): void
    {
        $rows = Menu::query()->get(['id', 'name', 'parent_id'])->toArray();
        $idToCode = [];
        foreach ($rows as $row) {
            $idToCode[(int) $row['id']] = (string) $row['name'];
        }
        foreach ($rows as $row) {
            $codeToId[(string) $row['name']] = (int) $row['id'];
            $parentOf[(string) $row['name']] = $idToCode[(int) $row['parent_id']] ?? '';
        }
    }

    /** @param string[] $patterns */
    private function allowed(string $code, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $code) {
                return true;
            }
            if (str_ends_with($pattern, '*') && str_starts_with($code, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    // ---------- Meta 小工具（基础种子那套字段太多，这里统一给默认值） ----------

    private function group(string $title, string $icon, int $sort): Meta
    {
        return new Meta([
            'title' => $title,
            'icon' => $icon,
            'type' => 'M',
            'hidden' => 0,
            'componentPath' => 'modules/',
            'componentSuffix' => '.vue',
            'breadcrumbEnable' => 1,
            'copyright' => 1,
            'cache' => 1,
            'affix' => 0,
        ]);
    }

    private function page(string $title, string $icon): Meta
    {
        return new Meta([
            'title' => $title,
            'icon' => $icon,
            'type' => 'M',
            'hidden' => 0,
            'componentPath' => 'modules/',
            'componentSuffix' => '.vue',
            'breadcrumbEnable' => 1,
            'copyright' => 1,
            'cache' => 1,
            'affix' => 0,
        ]);
    }

    /** 只做权限判断的节点，不在侧边栏出现。 */
    private function button(string $name, string $title): array
    {
        return ['name' => $name, 'meta' => new Meta(['title' => $title, 'type' => 'B'])];
    }

    /** 「运营配置」下 11 个页面的写法一样，只有标题/图标/目录不同。 */
    private function operation(string $name, string $dir, string $title, string $icon): array
    {
        return [
            'name' => $name,
            'path' => '/cl/operation/' . $dir,
            'component' => 'cl/views/operation/' . $dir . '/index',
            'meta' => $this->page($title, $icon),
        ];
    }
}
