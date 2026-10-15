<?php

declare(strict_types=1);

// 服务端按 Asia/Shanghai 算「在线」，造数据也要用同一个时区，否则造出来永远不在线
date_default_timezone_set('Asia/Shanghai');

// 本地演示数据：造几个用户/关系，方便在模拟器上走查各页面（不是生产种子）。
// 直接写库造几个用户，让 App 上能看到发现列表、访客、喜欢我的人。
// 口令不写死在仓库里：先取环境变量，再回退读不提交的 server/.env。
function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    static $file = null;
    if ($file === null) {
        $file = [];
        $path = __DIR__ . '/../server/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^\s*([A-Z0-9_]+)\s*=\s*(.*)$/', $line, $m)) {
                    $file[$m[1]] = trim($m[2], "\"'");
                }
            }
        }
    }

    return $file[$key] ?? $default;
}

$password = env_value('DB_PASSWORD');
if ($password === '') {
    fwrite(STDERR, "缺少数据库口令：在 server/.env 里设 DB_PASSWORD，或 DB_PASSWORD=xxx php scripts/seed-demo.php\n");
    exit(1);
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env_value('DB_HOST', '127.0.0.1'),
        env_value('DB_PORT', '3306'),
        env_value('DB_DATABASE', 'changliao')
    ),
    env_value('DB_USERNAME', 'root'),
    $password
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

const PASSWORD = 'abc12345';
$phones = ['13800000001', '13800000002', '13800000003', '13800000004', '13800000005'];

// 先清掉上次造的（连带关系与钱包）
$in = implode(',', array_fill(0, count($phones), '?'));
$ids = $pdo->prepare("SELECT id FROM cl_user WHERE phone IN ($in)");
$ids->execute($phones);
$userIds = array_map(static fn ($row) => (int) $row['id'], $ids->fetchAll());

if ($userIds !== []) {
    $idIn = implode(',', array_fill(0, count($userIds), '?'));
    foreach (['cl_profile_audit', 'cl_user_photo', 'cl_coin_wallet', 'cl_point_wallet', 'cl_coin_log', 'cl_point_log'] as $table) {
        $pdo->prepare("DELETE FROM {$table} WHERE user_id IN ($idIn)")->execute($userIds);
    }
    $pdo->prepare("DELETE FROM cl_visit WHERE visitor_id IN ($idIn)")->execute($userIds);
    $pdo->prepare("DELETE FROM cl_follow WHERE user_id IN ($idIn) OR target_id IN ($idIn)")->execute(array_merge($userIds, $userIds));
    $pdo->prepare("DELETE FROM cl_block WHERE user_id IN ($idIn) OR target_id IN ($idIn)")->execute(array_merge($userIds, $userIds));
    $pdo->prepare("DELETE FROM cl_user WHERE id IN ($idIn)")->execute($userIds);
}

/** @return int 新用户 id */
function makeUser(PDO $pdo, array $u): int
{
    $statement = $pdo->prepare(
        'INSERT INTO cl_user (account, phone, password, gender, nickname, avatar, bio, birthday, city, height, job,
            education, dating_purpose, tags, level, real_name_status, status, source, show_in_nearby,
            show_in_recommend, last_active_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 1, ?, NOW(), NOW())'
    );
    $statement->execute([
        $u['phone'], $u['phone'], password_hash(PASSWORD, PASSWORD_BCRYPT), $u['gender'], $u['nickname'],
        $u['avatar'], $u['bio'], $u['birthday'], $u['city'], $u['height'], $u['job'], $u['education'],
        $u['dating_purpose'], json_encode($u['tags']), $u['level'], $u['real_name_status'],
        date('Y-m-d H:i:s', time() - $u['active_minutes_ago'] * 60),
    ]);

    return (int) $pdo->lastInsertId();
}

$avatar = static fn (string $name): string => 'https://cdn.example.com/' . $name . '.jpg';

// 男号：登录用这个看发现页
//
// ⚠️ 这里的 `level` 是**直接写死的**（真实环境里等级由 `LevelService` 按累计收益算出来），
// 目的是让演示数据同时覆盖几个等级档位的单价/分成。所以会看到「晚晴 Lv3 而累计收益只有 1244」
// 这种状态 —— 那正是 `06` §5 允许的「后台手动调整等级」，`checkUpgrade` 不会把她降回去。
$male = makeUser($pdo, [
    'phone' => '13800000001', 'gender' => 1, 'nickname' => '阿宁', 'avatar' => $avatar('male'),
    'bio' => '慢热但真诚。下班后的快乐是做饭、跑步和一部好电影。', 'birthday' => '1996-03-05',
    'city' => '上海', 'height' => 178, 'job' => '产品经理', 'education' => '本科',
    'dating_purpose' => '认真交友', 'tags' => [2, 6], 'level' => 1, 'real_name_status' => 2,
    'active_minutes_ago' => 2,
]);

$xiaoman = makeUser($pdo, [
    'phone' => '13800000002', 'gender' => 2, 'nickname' => '小满', 'avatar' => $avatar('xiaoman'),
    'bio' => '喜欢逛展、拍照和在周末寻找好吃的面包店。希望遇到可以一起分享日常的人。',
    'birthday' => '2000-06-12', 'city' => '上海', 'height' => 165, 'job' => '品牌设计师',
    'education' => '本科', 'dating_purpose' => '认真交友', 'tags' => [1, 4], 'level' => 1,
    'real_name_status' => 2, 'active_minutes_ago' => 1,
]);

$wanqing = makeUser($pdo, [
    'phone' => '13800000003', 'gender' => 2, 'nickname' => '晚晴', 'avatar' => $avatar('wanqing'),
    'bio' => '想和你去看海，也想一起在家安静地听雨。', 'birthday' => '2001-09-20',
    'city' => '杭州', 'height' => 162, 'job' => '幼师', 'education' => '大专',
    'dating_purpose' => '兴趣搭子', 'tags' => [3, 10], 'level' => 3,
    'real_name_status' => 0, 'active_minutes_ago' => 30,
]);

$xingye = makeUser($pdo, [
    'phone' => '13800000004', 'gender' => 2, 'nickname' => '星野', 'avatar' => $avatar('xingye'),
    'bio' => '城市漫游爱好者，擅长发现不起眼但很美的小角落。', 'birthday' => '1999-01-15',
    'city' => '北京', 'height' => 168, 'job' => '摄影师', 'education' => '硕士',
    'dating_purpose' => '长期关系', 'tags' => [7], 'level' => 2,
    'real_name_status' => 0, 'active_minutes_ago' => 120,
]);

// 第二个男号：女号登录时能看到他
$linchuan = makeUser($pdo, [
    'phone' => '13800000005', 'gender' => 1, 'nickname' => '林川', 'avatar' => $avatar('linchuan'),
    'bio' => '健身、做饭、偶尔爬山。', 'birthday' => '1997-11-08', 'city' => '上海', 'height' => 182,
    'job' => '工程师', 'education' => '本科', 'dating_purpose' => '认真交友', 'tags' => [5],
    'level' => 1, 'real_name_status' => 1, 'active_minutes_ago' => 5,
]);

// 钱包：男号给点金币，女号给点积分（App 顶部要显示）。
//
// ⚠️ **每个号都要有钱包**：注册接口会给新用户建钱包（AppAuthService::register），
// 但这里是自己 INSERT 用户，漏了谁，谁就收不了付费私信（PointWalletService 找不到钱包会报错）。
$coinWallet = $pdo->prepare('INSERT INTO cl_coin_wallet (user_id, balance, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
$pointWallet = $pdo->prepare('INSERT INTO cl_point_wallet (user_id, balance, frozen, created_at, updated_at) VALUES (?, ?, 0, NOW(), NOW())');

$coinWallet->execute([$male, 360]);
$coinWallet->execute([$linchuan, 88]);
$pointWallet->execute([$xiaoman, 1200]);
$pointWallet->execute([$wanqing, 0]);
$pointWallet->execute([$xingye, 0]);

// 关注：小满 ↔ 阿宁（互关），晚晴 → 阿宁（单方，用来测「回关」）
$follow = $pdo->prepare('INSERT INTO cl_follow (user_id, target_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
$follow->execute([$xiaoman, $male]);
$follow->execute([$male, $xiaoman]);
$follow->execute([$wanqing, $male]);

// 访客：小满今天来了两次（同一天只算一条），晚晴昨天来过
$visit = $pdo->prepare('INSERT INTO cl_visit (visitor_id, target_id, visit_date, last_visit_at, read_at, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NULL, NOW(), NOW())');
$visit->execute([$xiaoman, $male, date('Y-m-d'), date('Y-m-d H:i:s')]);
$visit->execute([$wanqing, $male, date('Y-m-d', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('-1 day'))]);

echo "演示数据就绪：\n";
$rows = $pdo->query("SELECT id, phone, gender, nickname FROM cl_user WHERE phone IN ('13800000001','13800000002','13800000003','13800000004','13800000005') ORDER BY id")->fetchAll();
foreach ($rows as $row) {
    printf("  id=%d phone=%s gender=%d %s\n", $row['id'], $row['phone'], $row['gender'], $row['nickname']);
}
echo "  密码统一 " . PASSWORD . "\n";
