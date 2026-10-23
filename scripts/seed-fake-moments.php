<?php

declare(strict_types=1);

/**
 * 朋友圈演示数据（客户 2026-10-23 要求：男号和女号都要有朋友圈）。
 *
 * 在服务器上跑（PHP + PDO，不依赖框架）：
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/seed-fake-moments.php
 *
 * 它做四件事：
 *   1. 把仓库里的生活照（`scripts/assets/fake-moments/`）复制到 `storage/uploads/cl/moment/fake/`；
 *      服务器连不上外网，所以图片必须随仓库走；
 *   2. 清掉 bot 账号已有的动态/点赞/评论（可重复执行，不会越跑越多）；
 *   3. 给约 60% 的 bot 各造 2~5 条动态（时间散布最近 60 天，0~4 张图），
 *      再随机插点赞和评论，最后按真实行数回写计数；
 *   4. 给每个真实账号造 1 条，保证「我的朋友圈」不是空的。
 *
 * ⚠️ 只动 `account LIKE 'bot%'` 的演示账号，真实用户的历史动态不会被清。
 */

date_default_timezone_set('Asia/Shanghai');

const IMAGE_DIR = 'moment/fake';
const DAY_SECONDS = 86400;

const MALE_TEXTS = [
    '周末去爬山，山顶的风比想象中大。',
    '今天健身房人好多，练完直接躺平。',
    '加班到现在，楼下的便利店救了我。',
    '新买的手冲壶到了，第一杯不算难喝。',
    '球场上的兄弟说我今天手感不错。',
    '开车路过江边，停下来看了会儿晚霞。',
    '自己做的番茄牛腩，卖相一般但味道可以。',
    '出差第三天，想吃口热的。',
    '朋友推荐的这家面馆，确实值得排队。',
    '早上六点的城市，安静得像另一个地方。',
    '把桌子收拾干净了，心情也跟着好了。',
    '今天跑了十公里，腿有点抖。',
    '路过一家旧书店，淘到本不错的。',
    '下雨天，适合窝在家里打游戏。',
    '第一次尝试钓鱼，一条都没钓到。',
    '会议结束，终于能喘口气。',
    '新养的绿萝长得挺好。',
    '晚上做了个简单的沙拉，减脂第一天。',
    '这个周末打算什么都不干。',
    '路边的猫主动凑过来，蹭了我一脚。',
];

const FEMALE_TEXTS = [
    '下午茶时间，今天的蛋糕甜度刚好。',
    '楼下新开的花店，忍不住买了一束。',
    '猫又占了我的椅子，算了让它睡。',
    '逛街三小时，最后只买了一杯奶茶。',
    '新买的书到了，今晚不睡了。',
    '演唱会现场真的会让人起鸡皮疙瘩。',
    '早上做了瑜伽，感觉整个人都轻了。',
    '阳光正好，把被子拿出去晒了晒。',
    '和朋友吃了顿火锅，聊到很晚。',
    '路边的樱花开了，春天真的来了。',
    '今天试了新口红，颜色比想象中好看。',
    '窗台的多肉终于被我养活了。',
    '一个人看了场电影，也不错。',
    '做完手账，看到满满一页莫名治愈。',
    '下班路上遇到很好看的晚霞。',
    '给自己做了顿好看的早餐。',
    '换季整理衣柜，翻出好多没穿过的。',
    '雨停了，空气里有种干净的味道。',
    '今天走了两万步，脚要废了。',
    '海边风很大，但心情很好。',
];

const COMMENTS = [
    '好看！', '这也太惬意了吧', '下次带我一个', '在哪儿呀', '拍得真好看',
    '羡慕了', '同款生活', '这是哪里', '你最近状态不错', '哈哈哈可爱',
    '我也想去', '厉害了', '看着就好舒服', '这张很不错', '羡慕这种松弛感',
];

/**
 * 从 server/.env 读一个配置（不依赖框架）。
 */
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

$password = env_value('DB_PASSWORD');
if ($password === '') {
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
    $password,
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$baseUrl = rtrim(env_value('APP_URL', 'https://clapi.cyjiaoyou.lol'), '/');
$sourceDir = __DIR__ . '/assets/fake-moments';
$targetDir = __DIR__ . '/../server/storage/uploads/cl/' . IMAGE_DIR;

// ---------- 1. 图片 ----------

if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
    fwrite(STDERR, "无法创建图片目录：{$targetDir}\n");
    exit(1);
}

$files = glob($sourceDir . '/*.jpg') ?: [];
if ($files === []) {
    fwrite(STDERR, "仓库里没有演示图片：{$sourceDir}\n");
    exit(1);
}

$imageUrls = [];
foreach ($files as $file) {
    $name = basename($file);
    $target = $targetDir . '/' . $name;
    if (! is_file($target) || filesize($target) !== filesize($file)) {
        copy($file, $target);
    }
    @chmod($target, 0644);
    $imageUrls[] = $baseUrl . '/uploads/cl/' . IMAGE_DIR . '/' . $name;
}
@chmod($targetDir, 0755);

// ---------- 2. 清掉旧的演示动态 ----------

$botIds = array_map(
    static fn (array $row): int => (int) $row['id'],
    $pdo->query("SELECT id FROM cl_user WHERE account LIKE 'bot%'")->fetchAll(PDO::FETCH_ASSOC),
);

$realUsers = $pdo->query("SELECT id, gender FROM cl_user WHERE account NOT LIKE 'bot%'")->fetchAll(PDO::FETCH_ASSOC);

if ($botIds === []) {
    fwrite(STDERR, "没有找到 bot 演示账号，先跑 seed-fake-users.php\n");
    exit(1);
}

$pdo->exec('DELETE l FROM cl_moment_like l JOIN cl_moment m ON m.id = l.moment_id WHERE m.user_id IN (' . implode(',', $botIds) . ')');
$pdo->exec('DELETE c FROM cl_moment_comment c JOIN cl_moment m ON m.id = c.moment_id WHERE m.user_id IN (' . implode(',', $botIds) . ')');
$pdo->exec('DELETE FROM cl_moment WHERE user_id IN (' . implode(',', $botIds) . ')');

// 真实账号的演示动态也重置（只重置「我们造的这几条」没法区分，所以按用户全清，
// 但真实用户本来也还没发过 —— 真发过的话这里会一起清掉，跑之前确认一下）
$realIds = array_map(static fn (array $row): int => (int) $row['id'], $realUsers);
if ($realIds !== []) {
    $pdo->exec('DELETE l FROM cl_moment_like l JOIN cl_moment m ON m.id = l.moment_id WHERE m.user_id IN (' . implode(',', $realIds) . ')');
    $pdo->exec('DELETE c FROM cl_moment_comment c JOIN cl_moment m ON m.id = c.moment_id WHERE m.user_id IN (' . implode(',', $realIds) . ')');
    $pdo->exec('DELETE FROM cl_moment WHERE user_id IN (' . implode(',', $realIds) . ')');
}

// ---------- 3. 造动态 ----------

$genders = [];
foreach ($pdo->query("SELECT id, gender FROM cl_user WHERE account LIKE 'bot%'")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $genders[(int) $row['id']] = (int) $row['gender'];
}

$insertMoment = $pdo->prepare(
    'INSERT INTO cl_moment (user_id, content, images, like_count, comment_count, status, created_at, updated_at)
     VALUES (?, ?, ?, 0, 0, 1, ?, ?)',
);
$insertLike = $pdo->prepare('INSERT IGNORE INTO cl_moment_like (moment_id, user_id, created_at, updated_at) VALUES (?, ?, ?, ?)');
$insertComment = $pdo->prepare(
    'INSERT INTO cl_moment_comment (moment_id, user_id, content, status, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)',
);
$recount = $pdo->prepare(
    'UPDATE cl_moment SET like_count = (SELECT COUNT(*) FROM cl_moment_like WHERE moment_id = ?),
            comment_count = (SELECT COUNT(*) FROM cl_moment_comment WHERE moment_id = ? AND deleted_at IS NULL AND status = 1)
     WHERE id = ?',
);

$now = time();
$momentIds = [];
$inserted = 0;

$pdo->beginTransaction();
try {
    foreach ($botIds as $userId) {
        // 约 60% 的账号有朋友圈，其余留空更像真实用户
        if (random_int(1, 100) > 60) {
            continue;
        }

        $texts = ($genders[$userId] ?? 1) === 2 ? FEMALE_TEXTS : MALE_TEXTS;
        $count = random_int(2, 5);

        for ($i = 0; $i < $count; $i++) {
            $createdAt = $now - random_int(1, 60) * DAY_SECONDS - random_int(0, 86399);

            // 0~4 张图；纯文字和图文都允许
            $imageCount = min(random_int(0, 4), count($imageUrls));
            $images = [];
            if ($imageCount > 0) {
                foreach ((array) array_rand($imageUrls, $imageCount) as $k) {
                    $images[] = $imageUrls[(int) $k];
                }
            }

            $insertMoment->execute([
                $userId,
                $texts[random_int(0, count($texts) - 1)],
                json_encode(array_values($images), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                date('Y-m-d H:i:s', $createdAt),
                date('Y-m-d H:i:s', $createdAt),
            ]);
            $momentIds[] = (int) $pdo->lastInsertId();
            ++$inserted;
        }
    }

    // 真实账号各造 1 条，保证「我的朋友圈」不是空的
    foreach ($realUsers as $row) {
        $createdAt = $now - random_int(1, 20) * DAY_SECONDS;
        $images = [$imageUrls[random_int(0, count($imageUrls) - 1)]];
        $insertMoment->execute([
            (int) $row['id'],
            '记录一下今天。',
            json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            date('Y-m-d H:i:s', $createdAt),
            date('Y-m-d H:i:s', $createdAt),
        ]);
        $momentIds[] = (int) $pdo->lastInsertId();
        ++$inserted;
    }

    // ---------- 4. 点赞与评论 ----------

    foreach ($momentIds as $momentId) {
        $likeCount = random_int(0, 9);
        $used = [];
        for ($i = 0; $i < $likeCount; $i++) {
            $liker = $botIds[random_int(0, count($botIds) - 1)];
            if (isset($used[$liker])) {
                continue; // 同一个人只赞一次（否则撞唯一键）
            }
            $used[$liker] = true;
            $at = date('Y-m-d H:i:s', $now - random_int(1, 60) * DAY_SECONDS);
            $insertLike->execute([$momentId, $liker, $at, $at]);
        }

        $commentCount = random_int(0, 6);
        for ($i = 0; $i < $commentCount; $i++) {
            $commenter = $botIds[random_int(0, count($botIds) - 1)];
            $at = date('Y-m-d H:i:s', $now - random_int(1, 60) * DAY_SECONDS);
            $insertComment->execute([
                $momentId,
                $commenter,
                COMMENTS[random_int(0, count(COMMENTS) - 1)],
                $at,
                $at,
            ]);
        }

        $recount->execute([$momentId, $momentId, $momentId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '插入失败：' . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "完成：%d 张图、%d 条动态（其中真实账号 %d 条）\n",
    count($imageUrls),
    $inserted,
    count($realUsers),
);
