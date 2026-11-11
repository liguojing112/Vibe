<?php

declare(strict_types=1);

/**
 * 给 AI 男号换一批长短不一的网名（客户 2026-11-11）。
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/rename-ai-male-users.php
 *
 * 原来 500 个号全是四个字（「半山观云」「晚风叙旧」），一眼就是批量生成的。
 * 这里按长度分桶取名字：2 字 60 个、3 字 150 个、4 字 180 个、5 字 70 个、6 字 40 个，
 * 总数正好 500，看起来就像普通用户的网名。
 *
 * 只动 `account LIKE 'aim%'` 的机器人男号；使用固定随机种子，重复执行结果一致。
 */

date_default_timezone_set('Asia/Shanghai');

const ACCOUNT_PREFIX = 'aim';
const RANDOM_SEED = 20261111;

/** 2 字：口语化的老X / 阿X / 小X，加上常见的两字网名。 */
const NAME_2_CURATED = [
    '阿泽', '阿川', '老周', '老徐', '阿凯', '小马', '大鹏', '阿飞', '老陈', '阿龙',
    '阿城', '阿木', '子墨', '江河', '山海', '云深', '风起', '听雨', '归人', '行者',
    '闲人', '路人', '山客', '渔夫', '老张', '阿杰', '阿磊', '阿虎', '阿峰', '阿彬',
];

const NAME_2_PREFIXES = ['阿', '老', '小'];
const NAME_2_SUFFIXES = [
    '亮', '宇', '浩', '晨', '阳', '舟', '山', '海', '林', '江',
    '川', '风', '云', '雨', '松', '柏', '磊', '毅', '宁', '远',
];

/** 3 字：最常见的长度。 */
const NAME_3_CURATED = [
    '半山客', '晚风里', '一壶茶', '山野间', '云深处', '听风者', '慢先生', '江边人',
    '林间人', '溪边客', '山下行', '静水边', '闲话人', '半卷书', '一杯茶', '走山路',
    '看云人', '等风来', '拾光人', '一个人', '不着急', '慢慢走', '老派男', '阿泽哥',
    '山野客',
];

const NAME_3_PREFIXES = [
    '半山', '晚风', '云深', '松间', '竹里', '溪畔', '南窗', '北岸', '孤舟', '远山',
    '微凉', '初见', '归途', '旧巷', '枕月', '栖迟', '听雨', '临风', '山野', '茶伴',
];

const NAME_3_SUFFIXES = ['客', '人', '里', '间', '边', '下', '中'];

/** 4 字：保留客户原来给的那批，再加一些同类。 */
const NAME_4_CURATED = [
    '半山观云', '晚风叙旧', '浮生安渡', '且行且安', '清欢自渡', '闲观山海', '安然如故',
    '方寸之间', '茶伴余生', '静水垂纶', '山野寻闲', '一壶清茗', '晚风垂钓', '平凡度日',
    '随心而行', '听雨煮茶', '云深读月', '松间煮雪', '竹里听风', '溪畔拾光', '南窗读书',
    '北岸听涛', '孤舟问水', '远山含雪', '微凉知秋', '初见如故', '归途有风', '旧巷茶香',
    '枕月听风', '栖迟山林',
];

const NAME_4_PREFIXES = [
    '半山', '晚风', '浮生', '且行', '清欢', '闲观', '安然', '方寸', '茶伴', '静水',
    '山野', '一壶', '平凡', '随心', '听雨', '临风', '云深', '松间', '竹里', '溪畔',
    '南窗', '北岸', '孤舟', '远山', '微凉', '初见', '归途', '旧巷', '拾光', '枕月',
    '栖迟', '漫行', '晴窗', '落梅', '疏影', '流光', '素履', '青岚', '泊舟', '问山',
];

const NAME_4_SUFFIXES = [
    '观云', '叙旧', '安渡', '且安', '自渡', '观海', '如故', '之间', '余生', '垂纶',
    '寻闲', '清茗', '垂钓', '度日', '而行', '听雨', '看山', '煮茶', '读月', '候风',
    '渡己', '知秋', '等雪', '望云', '温酒', '煮雪', '拾花', '问水', '徐行', '闲坐',
    '观星', '听涛', '种花', '读书', '养心', '随缘', '知足', '自在', '清和', '简行',
];

/** 5 字：像一句话的网名。 */
const NAME_5_CURATED = [
    '一个人的海', '山野里的风', '慢慢来就好', '不擅长聊天', '话不多的人',
    '喜欢安静点', '下班去钓鱼', '周末去爬山', '在路上的风', '看云的小哥',
    '老城区散步', '喝茶看夕阳', '一个人住着', '不着急先生', '爱钓鱼的老哥',
];

const NAME_5_PLACES = [
    '山野里的', '城市里的', '雨天里的', '窗台前的',
    '巷子里的', '山脚下的', '河岸边的', '老街上的',
];
const NAME_5_THINGS = ['风', '云', '灯', '人', '猫', '雨'];

const NAME_5_MORE = [
    '晚上散散步', '下班去跑步', '不太爱说话', '慢一点也好', '山下的日子',
    '一个人散步', '半夜睡不着', '周末不出门', '不爱凑热闹', '话少但真诚',
];

/** 6 字（含以上）：完整的短句网名。 */
const NAME_6_CURATED = [
    '一个普通上班族', '喜欢安静的人', '不太会聊天的', '每天按时下班', '周末喜欢爬山',
    '一个人也挺好', '老城区散步的', '爱钓鱼的老哥', '没事就爱发呆', '话不多但真诚',
    '喜欢一个人待着', '不太爱凑热闹', '每天两点一线', '下班就想回家', '周末不爱出门',
    '一个人看电影', '山里的清晨', '老城区的黄昏', '喜欢慢慢来的', '话少但靠谱的',
];

const NAME_6_LIKES = ['安静', '钓鱼', '爬山', '喝茶', '走路', '看书'];
const NAME_6_ROLES = ['人', '老哥', '大叔'];
const NAME_6_PERSONALITIES = ['普通', '安静', '爱笑', '慢热'];

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

/**
 * 从一个名字池里取 $want 个不重复的。
 *
 * @param array<int,string> $names
 * @param array<string,bool> $used 已经用过的（跨桶去重）
 * @return array<int,string>
 */
function takeNames(array $names, int $want, array &$used): array
{
    $picked = [];

    foreach ($names as $name) {
        if (count($picked) >= $want) {
            break;
        }
        if ($name === '' || isset($used[$name])) {
            continue;
        }
        $used[$name] = true;
        $picked[] = $name;
    }

    return $picked;
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

$rows = $pdo->query(sprintf(
    "SELECT id FROM cl_user WHERE account LIKE '%s%%' AND gender = 1 ORDER BY id",
    ACCOUNT_PREFIX,
))->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    fwrite(STDERR, "没有找到 AI 男号（account LIKE 'aim%'）\n");
    exit(1);
}

$total = count($rows);
printf("==> AI 男号 %d 个\n", $total);

// ---------- 生成名字池 ----------

$used = [];
$name2 = NAME_2_CURATED;
foreach (NAME_2_PREFIXES as $prefix) {
    foreach (NAME_2_SUFFIXES as $suffix) {
        $name2[] = $prefix . $suffix;
    }
}

$name3 = NAME_3_CURATED;
foreach (NAME_3_PREFIXES as $prefix) {
    foreach (NAME_3_SUFFIXES as $suffix) {
        $name3[] = $prefix . $suffix;
    }
}

$name4 = NAME_4_CURATED;
foreach (NAME_4_PREFIXES as $prefix) {
    foreach (NAME_4_SUFFIXES as $suffix) {
        $name4[] = $prefix . $suffix;
    }
}

$name5 = NAME_5_CURATED;
foreach (NAME_5_PLACES as $place) {
    foreach (NAME_5_THINGS as $thing) {
        $name5[] = $place . $thing;
    }
}
$name5 = array_merge($name5, NAME_5_MORE);

$name6 = NAME_6_CURATED;
foreach (NAME_6_LIKES as $like) {
    foreach (NAME_6_ROLES as $role) {
        $name6[] = '喜欢' . $like . '的' . $role;
    }
}
foreach (NAME_6_PERSONALITIES as $personality) {
    $name6[] = '一个' . $personality . '的人';
}

// 按**实际字数**分桶：池子里混进了别的长度，不先筛一遍分布会跑偏
$byLength = static fn (array $names, int $min, int $max): array => array_values(array_filter(
    $names,
    static fn (string $name): bool => mb_strlen($name) >= $min && ($max === 0 || mb_strlen($name) <= $max),
));

$name2 = $byLength($name2, 2, 2);
$name3 = $byLength($name3, 3, 3);
$name4 = $byLength($name4, 4, 4);
$name5 = $byLength($name5, 5, 5);
$name6 = $byLength($name6, 6, 0);

// 按比例分桶，凑满 $total
$quotas = [
    ['names' => $name2, 'ratio' => 0.12],
    ['names' => $name3, 'ratio' => 0.30],
    ['names' => $name4, 'ratio' => 0.36],
    ['names' => $name5, 'ratio' => 0.14],
    ['names' => $name6, 'ratio' => 0.08],
];

$pools = [];
$assigned = 0;
foreach ($quotas as $index => $bucket) {
    $want = $index === count($quotas) - 1
        ? $total - $assigned
        : (int) round($total * $bucket['ratio']);
    $picked = takeNames($bucket['names'], $want, $used);
    if (count($picked) < $want) {
        fwrite(STDERR, sprintf("名字不够：第 %d 组只凑出 %d 个，需要 %d 个\n", $index + 1, count($picked), $want));
        exit(1);
    }
    $pools = array_merge($pools, $picked);
    $assigned += count($picked);
}

// 固定种子打散，保证重复执行结果一致
mt_srand(RANDOM_SEED);
shuffle($pools);

// ---------- 更新 ----------

$update = $pdo->prepare('UPDATE cl_user SET nickname = ? WHERE id = ?');

$pdo->beginTransaction();
try {
    foreach ($rows as $index => $row) {
        $update->execute([$pools[$index], (int) $row['id']]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '改名失败（已回滚）：' . $e->getMessage() . "\n");
    exit(1);
}

$lengths = array_count_values(array_map('mb_strlen', $pools));
ksort($lengths);
$summary = [];
foreach ($lengths as $len => $count) {
    $summary[] = $len . '字 ' . $count . ' 个';
}

printf("完成：更新 %d 个 AI 男号的昵称\n", count($rows));
printf("    长度分布：%s\n", implode('、', $summary));
printf("    示例：%s\n", implode('、', array_slice($pools, 0, 10)));
