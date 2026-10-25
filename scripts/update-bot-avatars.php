<?php

declare(strict_types=1);

/**
 * 把 bot 演示账号的头像换成新素材（客户 2026-10-25 提供的真人照片）。
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/update-bot-avatars.php
 *
 * 按性别轮流分配：f_001..f_099 给女号、m_001..m_065 给男号，账号数比图片多时会循环，
 * 但相邻账号不会拿到同一张（按 id 顺序轮流，不是随机）。
 *
 * 只动 `account LIKE 'bot%'` 的演示账号，真实用户不受影响；可重复执行。
 */

date_default_timezone_set('Asia/Shanghai');

const FEMALE_COUNT = 99;
const MALE_COUNT = 65;

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
$avatarUrl = $baseUrl . '/uploads/cl/fake';

$update = $pdo->prepare('UPDATE cl_user SET avatar = ? WHERE id = ?');
$summary = [];

foreach ([2 => ['f', FEMALE_COUNT], 1 => ['m', MALE_COUNT]] as $gender => [$prefix, $count]) {
    $rows = $pdo->query(sprintf(
        "SELECT id FROM cl_user WHERE account LIKE 'bot%%' AND gender = %d ORDER BY id",
        $gender,
    ))->fetchAll(PDO::FETCH_ASSOC);

    $index = 0;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            $fileName = sprintf('%s_%03d.jpg', $prefix, ($index % $count) + 1);
            $update->execute([$avatarUrl . '/' . $fileName, (int) $row['id']]);
            ++$index;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, '更新失败：' . $e->getMessage() . "\n");
        exit(1);
    }

    $summary[] = sprintf('%s号 %d 个', $gender === 2 ? '女' : '男', $index);
}

printf("完成：%s（头像来自 %s）\n", implode('、', $summary), $avatarUrl);
