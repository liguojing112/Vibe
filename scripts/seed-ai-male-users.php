<?php

declare(strict_types=1);

/**
 * 创建 500 个 AI 男号（客户 2026-11-10）。
 *
 * 直接在服务器上跑（PHP + PDO，不依赖框架）：
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/seed-ai-male-users.php              # 默认 500 个
 *   php ../scripts/seed-ai-male-users.php 500 Bot123456 # 指定统一密码（会导出 CSV）
 *
 * 和 `seed-fake-users.php` 的区别：
 * - **账号前缀 `aim`**，不和已有的 `bot*` 演示号混在一起，方便单独清理；
 * - 昵称是风景 / 隐逸风格的网名（半山观云、晚风叙旧…），不是真人姓名；
 * - 头像用 `scripts/assets/landscape-avatars/` 里的风景图（不是人像）；
 * - **不删任何已有账号**，只做追加；重复跑会接着往后编号，不会覆盖。
 *
 * 身份标记：`source = 2`、`batch_no = aimYYYYMMDDHHMMSS`。
 */

date_default_timezone_set('Asia/Shanghai');

const ACCOUNT_PREFIX = 'aim';
const AVATAR_KIND = 's';

/** 客户给的 15 个基准网名。 */
const BASE_NAMES = [
    '半山观云', '晚风叙旧', '浮生安渡', '且行且安', '清欢自渡',
    '闲观山海', '安然如故', '方寸之间', '茶伴余生', '静水垂纶',
    '山野寻闲', '一壶清茗', '晚风垂钓', '平凡度日', '随心而行',
];

/** 组合用的前缀（自然意象）。 */
const NAME_A = [
    '半山', '晚风', '浮生', '且行', '清欢', '闲观', '安然', '方寸', '茶伴', '静水',
    '山野', '一壶', '平凡', '随心', '听雨', '临风', '云深', '松间', '竹里', '溪畔',
    '南窗', '北岸', '孤舟', '远山', '微凉', '初见', '归途', '旧巷', '拾光', '枕月',
    '栖迟', '漫行', '晴窗', '落梅', '疏影', '流光', '素履', '青岚', '泊舟', '问山',
];

/** 组合用的后缀（动作 / 心境）。 */
const NAME_B = [
    '观云', '叙旧', '安渡', '且安', '自渡', '观海', '如故', '之间', '余生', '垂纶',
    '寻闲', '清茗', '垂钓', '度日', '而行', '听雨', '看山', '煮茶', '读月', '候风',
    '渡己', '知秋', '等雪', '望云', '温酒', '煮雪', '拾花', '问水', '徐行', '闲坐',
    '观星', '听涛', '种花', '读书', '养心', '随缘', '知足', '自在', '清和', '简行',
];

const BIOS = [
    '喜欢在山里走走，看看云。',
    '日子过得慢一点也挺好。',
    '周末泡壶茶，翻两页书。',
    '话不多，但愿意听你说。',
    '喜欢钓鱼，也喜欢安静。',
    '习惯一个人，也不排斥多个人。',
    '看山看水，随遇而安。',
    '不太会聊天，但很真诚。',
];

const CITIES = ['上海', '北京', '杭州', '深圳', '广州', '成都', '武汉', '南京', '苏州', '重庆', '西安', '长沙', '天津', '青岛', '宁波', '厦门'];

function env_value(string $key, string $default = ''): string
{
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

$dbPassword = env_value('DB_PASSWORD');
if ($dbPassword === '') {
    fwrite(STDERR, "缺少数据库口令：在 server/.env 里设 DB_PASSWORD\n");
    exit(1);
}

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env_value('DB_HOST', '127.0.0.1'),
        env_value('DB_PORT', '3306'),
        env_value('DB_DATABASE', 'changliao'),
    ),
    env_value('DB_USERNAME', 'root'),
    $dbPassword,
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$count = (int) ($argv[1] ?? 500);
$count = max(1, min(2000, $count));
$plainPassword = (string) ($argv[2] ?? '');

$baseUrl = rtrim(env_value('APP_URL', 'https://clapi.cyjiaoyou.lol'), '/');
$avatarDir = __DIR__ . '/../server/storage/uploads/cl/fake';
$avatarUrl = $baseUrl . '/uploads/cl/fake';

// ---------- 1. 头像 ----------

if (! is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

// 男头像 + 风景图一起用，重复度更低
$maleSources = glob(__DIR__ . '/assets/ai-male-avatars/a_*.jpg') ?: [];
$landSources = glob(__DIR__ . '/assets/landscape-avatars/' . AVATAR_KIND . '_*.jpg') ?: [];
sort($maleSources);
sort($landSources);
$sources = array_merge($maleSources, $landSources);

if ($sources === []) {
    fwrite(STDERR, "缺少头像素材：scripts/assets/ai-male-avatars/ 或 landscape-avatars/\n");
    exit(1);
}

$avatars = [];
foreach ($sources as $source) {
    $file = basename($source);
    $target = $avatarDir . '/' . $file;

    if (! is_file($target) || filesize($target) < 1000) {
        copy($source, $target);
        @chmod($target, 0644);
    }

    $avatars[] = $avatarUrl . '/' . $file;
}

printf("==> 风景头像 %d 张就绪（%s）\n", count($avatars), $avatarDir);

// ---------- 2. 昵称 ----------

/** 生成 $count 个不重复的风景网名。基准名优先，不够就用组合。 */
function buildNames(int $count): array
{
    $names = BASE_NAMES;
    $seen = array_fill_keys($names, true);

    // 先按「前缀 + 后缀」铺开，顺序打散，避免相邻账号名字太像
    $combos = [];
    foreach (NAME_A as $a) {
        foreach (NAME_B as $b) {
            $name = $a . $b;
            if (! isset($seen[$name]) && mb_strlen($name) <= 8) {
                $combos[] = $name;
            }
        }
    }
    shuffle($combos);

    foreach ($combos as $name) {
        if (count($names) >= $count) {
            break;
        }
        if (isset($seen[$name])) {
            continue;
        }
        $seen[$name] = true;
        $names[] = $name;
    }

    if (count($names) < $count) {
        fwrite(STDERR, sprintf("昵称不够：只凑出 %d 个，需要 %d 个\n", count($names), $count));
        exit(1);
    }

    return array_slice($names, 0, $count);
}

$names = buildNames($count);
printf("==> 昵称 %d 个就绪\n", count($names));

// ---------- 3. 账号 ----------

$batchNo = 'aim' . date('YmdHis');

// 接着现有最大编号往后排，不覆盖已有账号
$stmt = $pdo->prepare('SELECT MAX(CAST(SUBSTRING(account, ?) AS UNSIGNED)) FROM cl_user WHERE account LIKE ?');
$stmt->execute([strlen(ACCOUNT_PREFIX) + 1, ACCOUNT_PREFIX . '%']);
$start = max(1, (int) $stmt->fetchColumn() + 1);

$passwordHash = password_hash($plainPassword === '' ? bin2hex(random_bytes(16)) : $plainPassword, PASSWORD_BCRYPT);

$insertUser = $pdo->prepare(
    'INSERT INTO cl_user (account, phone, password, gender, nickname, avatar, bio, city, level, status, source, batch_no, last_active_at, created_at, updated_at)
     VALUES (?, NULL, ?, 1, ?, ?, ?, ?, 1, 1, 2, ?, ?, ?, ?)'
);
$insertCoin = $pdo->prepare('INSERT INTO cl_coin_wallet (user_id, balance, total_recharge, total_spent, created_at, updated_at) VALUES (?, 0, 0, 0, ?, ?)');
$insertPoint = $pdo->prepare('INSERT INTO cl_point_wallet (user_id, balance, frozen, total_withdrawn, created_at, updated_at) VALUES (?, 0, 0, 0, ?, ?)');
$insertBatch = $pdo->prepare(
    'INSERT INTO cl_batch_account (batch_no, gender, count, account_prefix, operator_id, remark, exported, created_at, updated_at)
     VALUES (?, 1, ?, ?, NULL, ?, 0, ?, ?)'
);

$now = date('Y-m-d H:i:s');
$rows = [];

$pdo->beginTransaction();
try {
    $insertBatch->execute([$batchNo, $count, ACCOUNT_PREFIX, 'AI 互聊男号（风景网名）', $now, $now]);

    for ($i = 0; $i < $count; ++$i) {
        $account = ACCOUNT_PREFIX . str_pad((string) ($start + $i), 8, '0', STR_PAD_LEFT);

        $insertUser->execute([
            $account,
            $passwordHash,
            $names[$i],
            $avatars[$i % count($avatars)],
            BIOS[$i % count(BIOS)],
            CITIES[$i % count(CITIES)],
            $batchNo,
            $now,
            $now,
            $now,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $insertCoin->execute([$userId, $now, $now]);
        $insertPoint->execute([$userId, $now, $now]);

        $rows[] = [$account, $names[$i]];
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '创建失败（已回滚）：' . $e->getMessage() . "\n");
    exit(1);
}

printf("完成：新建 %d 个 AI 男号\n", count($rows));
printf("    批次号：%s\n", $batchNo);
printf("    账号：%s%08d ~ %s%08d\n", ACCOUNT_PREFIX, $start, ACCOUNT_PREFIX, $start + $count - 1);
printf("    昵称示例：%s\n", implode('、', array_slice(array_column($rows, 1), 0, 8)));

if ($plainPassword !== '') {
    $csvPath = sprintf('%s/ai-male-accounts-%s.csv', sys_get_temp_dir(), $batchNo);
    $fp = fopen($csvPath, 'wb');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, ['账号', '昵称', '密码']);
    foreach ($rows as [$account, $nickname]) {
        fputcsv($fp, [$account, $nickname, $plainPassword]);
    }
    fclose($fp);
    printf("    明文已导出：%s\n", $csvPath);
}
