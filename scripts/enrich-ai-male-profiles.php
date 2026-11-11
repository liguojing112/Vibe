<?php

declare(strict_types=1);

/**
 * 给 AI 男号补齐资料、换一批风景头像（客户 2026-11-11）。
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/enrich-ai-male-profiles.php
 *
 * 只动 `account LIKE 'aim%'` 的机器人男号，真实用户不受影响；可重复执行。
 *
 * 做三件事：
 *   1. 把 `scripts/assets/landscape-avatars/` 里的风景图同步到上传目录；
 *   2. 按 id 轮流重新分配头像 —— 45 张图分给 500 个号，相邻账号不会撞图；
 *   3. 补全资料：自我介绍、职业、身高、学历、交友目的、生日、城市。
 *      每一项都从素材池里散开取，避免「一页全是同一句简介」。
 */

date_default_timezone_set('Asia/Shanghai');

const ACCOUNT_PREFIX = 'aim';

const BIOS = [
    '周末喜欢去山里走走，信号不好也没关系。',
    '一个人住了几年，做饭的手艺还行。',
    '不算会聊天，但你说的我都会认真听。',
    '喜欢钓鱼，一坐一下午，鱼没钓到也挺开心。',
    '下班回家泡壶茶，翻两页书就过去了。',
    '最近在学做菜，进步缓慢但很有热情。',
    '喜欢老城区，走走停停，看别人怎么生活。',
    '不太爱热闹，人多的饭局能躲就躲。',
    '工作比较忙，但周末一般都能空出来。',
    '喜欢拍照，拍的都是些没人的角落。',
    '开车出去不设目的地，走到哪算哪。',
    '养了一盆绿萝，居然活了三年。',
    '喜欢老电影，最近在重看一些八九十年代的。',
    '不太会浪漫，但答应的事一定做到。',
    '喜欢清晨，街上还没人的时候最好。',
    '会做饭，会修点小东西，日子过得下去。',
    '喜欢下雨天，不用出门的那种。',
    '一个人吃过很多次火锅，也没什么不好。',
    '希望认识一个能一起安静待着的人。',
    '平时话不多，熟了之后是另一个样子。',
    '喜欢爬山，登顶那一下最舒服。',
    '喜欢喝咖啡，也喜欢在路边摊吃面。',
    '工作稳定，生活简单，没什么大起大落。',
    '最近在练跑步，从三公里开始。',
    '喜欢看纪录片，尤其是讲自然的。',
    '不太会用表情包，但我努力了。',
    '喜欢慢一点的关系，急不来。',
    '会下厨，会泡茶，也会安静地坐着。',
    '朋友圈很少发，日子都过在生活里。',
    '喜欢去人少的公园，看老人下棋。',
    '做事比较稳，不喜欢临时改计划。',
    '喜欢听老歌，也听得进去新东西。',
    '不太追求热闹，舒服最重要。',
    '喜欢逛菜市场，比逛商场有意思。',
    '有点慢热，但真诚。',
    '喜欢山，喜欢水，也喜欢一个人待着。',
    '生活规律，早睡早起，偶尔熬夜看球。',
    '喜欢做手工，做的东西不好看但用心。',
    '愿意花时间了解一个人，而不是匆匆开始。',
    '喜欢安静，但不排斥偶尔的热闹。',
];

const JOBS = [
    '软件工程师', '产品经理', '结构工程师', '项目经理', '中学教师', '会计',
    '平面设计', '室内设计', '摄影师', '医生', '药剂师', '律师助理',
    '咖啡师', '健身教练', '物流主管', '银行职员', '建筑设计师', '编辑',
    '设备维修', '园林绿化', '外贸业务', '行政主管',
];

const EDUCATIONS = ['大专', '本科', '本科', '本科', '硕士'];

const DATING_PURPOSES = ['认真交友', '认真交友', '长期关系', '兴趣搭子'];

const CITIES = [
    '上海', '北京', '杭州', '深圳', '广州', '成都', '武汉', '南京',
    '苏州', '重庆', '西安', '长沙', '天津', '青岛', '宁波', '厦门',
    '合肥', '郑州', '济南', '福州', '昆明', '无锡',
];

const HEIGHTS = [170, 172, 174, 175, 176, 178, 180, 182, 185];

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

$baseUrl = rtrim(env_value('APP_URL', 'https://clapi.cyjiaoyou.lol'), '/');
$avatarDir = __DIR__ . '/../server/storage/uploads/cl/fake';
$avatarUrl = $baseUrl . '/uploads/cl/fake';

// ---------- 1. 头像 ----------

if (! is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

// 客户提供的男头像 + 风景图一起用：500 个号分到 200+ 张，重复度更低
$maleSources = glob(__DIR__ . '/assets/ai-male-avatars/a_*.jpg') ?: [];
$landSources = glob(__DIR__ . '/assets/landscape-avatars/s_*.jpg') ?: [];
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

printf("==> 头像素材 %d 张就绪\n", count($avatars));

// ---------- 2. 取账号 ----------

$rows = $pdo->query(sprintf(
    "SELECT id, nickname FROM cl_user WHERE account LIKE '%s%%' AND gender = 1 ORDER BY id",
    ACCOUNT_PREFIX,
))->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    fwrite(STDERR, "没有找到 AI 男号（account LIKE 'aim%'）\n");
    exit(1);
}

printf("==> AI 男号 %d 个\n", count($rows));

// ---------- 3. 逐条更新 ----------

$update = $pdo->prepare(
    'UPDATE cl_user SET avatar = ?, bio = ?, job = ?, education = ?, dating_purpose = ?, height = ?, birthday = ?, city = ? WHERE id = ?'
);

$rnd = static function (array $pool, int $index, int $offset): string {
    $n = count($pool);

    return (string) $pool[($index * 7 + $offset) % $n];
};

$pdo->beginTransaction();
try {
    foreach ($rows as $index => $row) {
        $height = $rnd(array_map('strval', HEIGHTS), $index, 3);
        $age = 24 + ($index * 5) % 15;                 // 24~38 岁
        $birthday = sprintf('%d-%02d-%02d',
            (int) date('Y') - $age,
            1 + ($index * 3) % 12,
            1 + ($index * 11) % 28,
        );

        $update->execute([
            $avatars[$index % count($avatars)],
            $rnd(BIOS, $index, 0),
            $rnd(JOBS, $index, 1),
            $rnd(EDUCATIONS, $index, 2),
            $rnd(DATING_PURPOSES, $index, 4),
            (int) $height,
            $birthday,
            $rnd(CITIES, $index, 5),
            (int) $row['id'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '更新失败（已回滚）：' . $e->getMessage() . "\n");
    exit(1);
}

printf("完成：更新 %d 个 AI 男号的头像与资料\n", count($rows));
printf("    头像 %d 张轮换，简介 %d 条、职业 %d 种、城市 %d 个\n", count($avatars), count(BIOS), count(JOBS), count(CITIES));
