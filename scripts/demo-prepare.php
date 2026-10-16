<?php

declare(strict_types=1);

/**
 * 本地演示数据准备。**不新增账号**，只把 seed-demo.php 造的 5 个号掰到「客户看不出破绽」的状态。
 *
 * 跑一次就行（幂等，重复跑无副作用）：
 *   wsl -d Ubuntu -u root -- bash -lc 'cd /mnt/c/Workspace/android/vibe/changliao/server && php ../scripts/demo-prepare.php'
 *
 * 它做四件事：
 *
 * 1. **取消阿宁 → 小满的互关**。`chat.mutual_follow_free=1`，互关后发消息**完全免费** ——
 *    留着的话，客户点「发送」看不到扣币，会以为计费坏了。改成单向关注（阿宁→小满），
 *    P05 上显示「关注」按钮，正好能演示关注/取消关注。小满→阿宁 保留（P09 有一条「喜欢我的人」）。
 * 2. **只留 5 个演示号参与推荐**。库里还有 E2E、WS、批量号等测试残留，客户在发现页看到
 *    「用户0001」会问这是什么。E2E 的号没有手机号，所以按昵称再补一刀。
 * 3. **头像换成能加载的图**。seed 用的是 `https://cdn.example.com/*.jpg`，域名不存在，
 *    模拟器里全是裂图。改用 picsum 的固定 seed。
 * 4. **确保阿宁是会员**（客户 2026-10-15 明确要「先看会员权益」）。他的会员本来到 2026-11-12
 *    （3 张月卡订单累计）；如果被清过就按原值恢复，**没被清过就绝不动**。
 *
 * 结尾会打印当前状态和「怎么让客户看到扣费」—— 因为会员每天免 10 条会掩盖收费。
 */

date_default_timezone_set('Asia/Shanghai');

$pdo = new PDO('mysql:host=127.0.0.1;dbname=changliao;charset=utf8mb4', 'root', 'root123');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$demoPhones = ['13800000001', '13800000002', '13800000003', '13800000004', '13800000005'];

/** 阿宁原本的会员到期时间：10-13 20:32 开月卡 → 21:02 又开一张，累计到 2026-11-12 21:02:25 */
const ANNING_VIP_EXPIRE = '2026-11-12 21:02:25';

function say(string $msg): void
{
    echo $msg, "\n";
}

say('== 1. 取消阿宁 → 小满的互关 ==');
$byPhone = [];
$ids = $pdo->prepare('SELECT id, phone FROM cl_user WHERE phone IN (?, ?)');
$ids->execute(['13800000001', '13800000002']);
foreach ($ids->fetchAll() as $r) {
    $byPhone[$r['phone']] = (int) $r['id'];
}
if (isset($byPhone['13800000001'], $byPhone['13800000002'])) {
    $dead = $pdo->prepare('DELETE FROM cl_follow WHERE user_id = ? AND target_id = ?');
    $dead->execute([$byPhone['13800000001'], $byPhone['13800000002']]);
    say(sprintf('  阿宁(%d) → 小满(%d) 已删；小满 → 阿宁 保留（P09 有一条「喜欢我的人」）',
        $byPhone['13800000001'], $byPhone['13800000002']));
} else {
    say('  ⚠️ 没找到演示账号，先跑 scripts/seed-demo.php');
}

say('== 2. 只留 5 个演示号参与推荐 ==');
$hold = implode(',', array_fill(0, count($demoPhones), '?'));
$off = $pdo->prepare("UPDATE cl_user SET show_in_recommend = 0, show_in_nearby = 0 WHERE phone NOT IN ($hold)");
$off->execute($demoPhones);
say(sprintf('  关掉 %d 个测试残留号', $off->rowCount()));

// E2E 的号没有手机号，NOT IN 抓不到，按昵称再补一刀
$e2e = $pdo->prepare("UPDATE cl_user SET show_in_recommend = 0, show_in_nearby = 0 WHERE nickname LIKE 'E2E%'");
$e2e->execute();
say(sprintf('  另外关掉 %d 个 E2E 测试号（无手机号）', $e2e->rowCount()));

$on = $pdo->prepare("UPDATE cl_user SET show_in_recommend = 1, show_in_nearby = 1 WHERE phone IN ($hold)");
$on->execute($demoPhones);
say(sprintf('  演示号确认开启：%d 个', $on->rowCount()));

say('== 3. 头像换成能加载的图 ==');
$avatar = $pdo->prepare('UPDATE cl_user SET avatar = ? WHERE phone = ?');
$picsum = static fn (string $seed): string => 'https://picsum.photos/seed/' . $seed . '/600/600';
foreach ([
    '13800000001' => 'cl-male',
    '13800000002' => 'cl-xiaoman',
    '13800000003' => 'cl-wanqing',
    '13800000004' => 'cl-xingye',
    '13800000005' => 'cl-linchuan',
] as $phone => $seed) {
    $avatar->execute([$picsum($seed), $phone]);
}
say('  5 个头像指向 picsum（固定 seed，刷新不变）');

say('== 4. 刷新女号的活跃时间（「在线」= 15 分钟内活跃过，见 Q0-19）==');
// seed 用 PHP 的 date() 写 last_active_at，CLI 时区可能是 UTC，写出来「不在线」，
// 演示时发现页「推荐」Tab 就是空的（T9.6 踩到）。男号的不动 —— 阿宁登录后服务端会自己刷。
$touch = $pdo->prepare('UPDATE cl_user SET last_active_at = NOW() WHERE phone = ? AND gender = 2');
foreach (['13800000002', '13800000003', '13800000004'] as $phone) {
    $touch->execute([$phone]);
}
say(sprintf('  小满/晚晴/星野 的 last_active_at 刷成现在（%s）', date('Y-m-d H:i:s')));

say('== 5. 阿宁保持会员（客户要「先看会员权益」）==');
$vip = $pdo->prepare('SELECT id, nickname, vip_expire_at FROM cl_user WHERE phone = ?');
$vip->execute(['13800000001']);
$row = $vip->fetch();
if ($row === false) {
    say('  ⚠️ 没找到阿宁');
} else {
    $expire = $row['vip_expire_at'];
    if ($expire !== null && strtotime($expire) > time()) {
        say(sprintf('  已是会员（%s 到期），不动', substr($expire, 0, 10)));
    } else {
        $fix = $pdo->prepare('UPDATE cl_user SET vip_expire_at = ? WHERE id = ?');
        $fix->execute([ANNING_VIP_EXPIRE, (int) $row['id']]);
        say(sprintf('  会员被清过，已恢复到 %s', ANNING_VIP_EXPIRE));
    }
}

say('== 6. 待审资料 ==');
foreach ($pdo->query('SELECT COUNT(*) AS n FROM cl_profile_audit WHERE status = 0') as $r) {
    say(sprintf('  待审 %d 条。要演示「资料审核」就故意留着（P05 上对方显示审核中）；'
        . '要顺当走完跑 UPDATE cl_profile_audit SET status=1 WHERE status=0', (int) $r['n']));
}

say('');
say('== 当前状态 ==');
$show = $pdo->prepare('SELECT u.phone, u.nickname, u.gender, u.level, u.real_name_status,
                             u.show_in_recommend, u.vip_expire_at, c.balance AS coins, p.balance AS points
                      FROM cl_user u
                      LEFT JOIN cl_coin_wallet c ON c.user_id = u.id
                      LEFT JOIN cl_point_wallet p ON p.user_id = u.id
                      WHERE u.phone IN (' . implode(',', array_fill(0, count($demoPhones), '?')) . ')
                      ORDER BY u.id');
$show->execute($demoPhones);
foreach ($show->fetchAll() as $r) {
    printf("  %s %-6s Lv%s 实名=%s 推荐=%s 会员=%-10s 金币=%-5s 积分=%s\n",
        $r['gender'] == 1 ? '男' : '女', $r['nickname'], $r['level'], $r['real_name_status'],
        $r['show_in_recommend'], $r['vip_expire_at'] ? substr($r['vip_expire_at'], 0, 10) : '无',
        $r['coins'] ?? '-', $r['points'] ?? '-');
}

say('');
say('== 怎么让客户看到「真的在收费」 ==');
say('  阿宁是会员：每对前 3 条免费 + 会员每日 10 条免费，连发 14 条才扣币。三条路任选：');
say('    A. 后台把 chat.free_count_per_pair 改成 0，第 1 条就扣（演示前改，演示后改回 3）');
say('    B. 把阿宁的会员关掉：UPDATE cl_user SET vip_expire_at = NULL WHERE phone = 13800000001');
say('    C. 让客户先发 3 条免费的，等对方回复，再连着发到底出现扣费');
say('  送礼和通话不受免费条数影响，随时能看到扣费。');
say('');
say('登录：13800000001（阿宁，男）/ 13800000002（小满，女），密码 abc12345');
say('App 已装到 emulator-5554，指向 http://127.0.0.1:9501（adb reverse）。');
