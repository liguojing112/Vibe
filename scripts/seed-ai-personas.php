<?php

declare(strict_types=1);

/**
 * 给 bot 女号批量生成 AI 代聊人设（演示 / 试点用）。
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/seed-ai-personas.php          # 默认 30 个
 *   php ../scripts/seed-ai-personas.php 60       # 自定义数量
 *
 * 只处理 `account LIKE 'bot%'` 的女号，已经有配置的会跳过（可重复跑）。
 * 生成的人设默认开启、主动程度中等、活跃时段 9:00-23:00。
 */

date_default_timezone_set('Asia/Shanghai');

const DEFAULT_COUNT = 30;

const PERSONA_TEMPLATES = [
    '外表随和，内心有点挑剔。喜欢看电影，会记笔记，但生活里丢三落四。',
    '慢热，熟了以后话很多。周末喜欢一个人逛菜市场，回家做饭。',
    '看起来高冷，其实很好说话。喜欢猫，养了一只，很黏人。',
    '爱笑，笑点很低。下班喜欢散步，偶尔去健身房，但坚持不下来。',
    '喜欢安静的地方，咖啡店、书店、傍晚的江边。不太会主动，但聊起来不冷场。',
    '有点宅，喜欢追剧和纪录片。天气好的时候会出门拍照。',
    '在努力早睡但总是失败。喜欢听歌，通勤路上一直在听。',
    '喜欢旅行，但更喜欢计划旅行的过程。去过几个城市，最喜欢成都。',
    '工作忙的时候很忙，闲的时候能睡一整天。喜欢和朋友吃火锅。',
    '性格直，说话不绕弯。喜欢打羽毛球，最近在学做甜品。',
];

const TONE_TEMPLATES = [
    '像发微信一样，短句，偶尔带点语气词',
    '说话比较直接，不太会用表情包，但句尾爱加个「哈」',
    '温柔一点，语速慢，喜欢问对方感受',
    '活泼，爱开玩笑，常用「哈哈」「绝了」',
    '安静型，说话简短，但偶尔冒一句金句',
];

const TABOOS = '政治、血腥恐怖、催婚、收入细节';

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

$count = max(1, min(500, (int) ($argv[1] ?? DEFAULT_COUNT)));

$candidates = $pdo->query(
    "SELECT u.id, u.nickname, u.city, u.job, u.bio
     FROM cl_user u
     LEFT JOIN cl_ai_persona p ON p.user_id = u.id
     WHERE u.account LIKE 'bot%' AND u.gender = 2 AND p.id IS NULL
     ORDER BY RAND()
     LIMIT " . $count,
)->fetchAll(PDO::FETCH_ASSOC);

if ($candidates === []) {
    echo "没有可生成的女号（可能都已经配过）\n";
    exit(0);
}

$insert = $pdo->prepare(
    'INSERT INTO cl_ai_persona
        (user_id, enabled, persona, tone, taboos, proactive, active_start, active_end, daily_limit, status, created_at, updated_at)
     VALUES (?, 1, ?, ?, ?, 2, 9, 23, 80, 1, NOW(), NOW())',
);

$pdo->beginTransaction();
try {
    foreach ($candidates as $row) {
        // 人设 = 模板 + 她自己的职业/城市，读起来像同一个人
        $persona = PERSONA_TEMPLATES[array_rand(PERSONA_TEMPLATES)];
        if (($row['job'] ?? '') !== '') {
            $persona .= "平时做{$row['job']}。";
        }
        if (($row['bio'] ?? '') !== '') {
            $persona .= '她自己说：' . $row['bio'];
        }

        $insert->execute([
            (int) $row['id'],
            $persona,
            TONE_TEMPLATES[array_rand(TONE_TEMPLATES)],
            TABOOS,
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '生成失败：' . $e->getMessage() . "\n");
    exit(1);
}

printf("完成：为 %d 个 bot 女号生成了 AI 代聊人设\n", count($candidates));
