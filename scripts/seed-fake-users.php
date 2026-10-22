<?php

declare(strict_types=1);

/**
 * 生产环境演示账号种子（客户 2026-10-22 要求：广场里要有「人」，男用户多一点）。
 *
 * 直接在服务器上跑（PHP + PDO，不依赖框架）：
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/seed-fake-users.php                    # 默认 300 个（男 180 / 女 120）
 *   php ../scripts/seed-fake-users.php 500                # 自定义数量
 *   php ../scripts/seed-fake-users.php 500 Bot123456     # 指定统一密码（会导出 CSV）
 *
 * 它做四件事：
 *   1. 把仓库里的头像（`scripts/assets/fake-avatars/`）复制到 `storage/uploads/cl/fake/`；
 *      仓库里没有的才去 pravatar 下载（服务器通常连不上外网，所以头像随仓库走）；
 *   2. 删掉上次造的 `bot%` 账号（连带钱包），保证可重复跑；
 *   3. 按男女比例插入用户 + 钱包；
 *   4. `last_active_at` 设成最近几分钟，这样在发现页是「在线」。
 *
 * ⚠️ 这些是**演示数据**，不是真实用户：
 *   - 账号统一以 `bot` 开头，方便以后一条 SQL 清掉；
 *   - 密码默认是随机串（谁也不知道）；传了第二个参数就用那个口令，并把账号导出成 CSV；
 *   - 相册（`cl_user_photo`）不造，避免审核中心一堆待审。
 */

date_default_timezone_set('Asia/Shanghai');

const BOT_PREFIX = 'bot';

/** 允许的头像编号（从 pravatar 的 60 张里挑的成年男女，去掉了儿童和恶搞图）。 */
const MALE_PORTRAITS = [1, 3, 6, 7, 8, 11, 12, 13, 14, 15, 17, 18, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60];
const FEMALE_PORTRAITS = [5, 9, 10, 16, 19, 20, 21, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49];

const SURNAMES = ['王', '李', '张', '刘', '陈', '杨', '赵', '黄', '周', '吴', '徐', '孙', '胡', '朱', '高', '林',
    '何', '郭', '马', '罗', '梁', '宋', '郑', '谢', '韩', '唐', '冯', '董', '萧', '程', '曹', '袁', '邓', '许',
    '沈', '曾', '彭', '苏', '卢', '蒋', '蔡', '贾', '丁', '魏', '薛', '叶', '余', '潘', '杜', '戴', '夏', '钟'];

const MALE_GIVEN = ['伟', '强', '磊', '军', '洋', '勇', '杰', '涛', '明', '超', '鹏', '斌', '宇', '浩', '凯', '旭',
    '峰', '晨', '昊', '轩', '毅', '睿', '泽', '航', '铭', '哲', '川', '一鸣', '子墨', '思远', '嘉豪', '亦辰'];

const FEMALE_GIVEN = ['小满', '晚晴', '星野', '一诺', '思彤', '雅静', '梓涵', '雨欣', '佳怡', '若溪', '梦琪',
    '诗涵', '静怡', '晓彤', '语嫣', '心怡', '可欣', '依诺', '婉清', '沐雪', '清欢', '南初', '安然', '初夏',
    '微光', '拾光', '知夏', '念安', '书瑶', '以宁', '木槿', '青禾'];

const CITIES = ['上海', '北京', '杭州', '深圳', '广州', '成都', '武汉', '南京', '苏州', '重庆', '西安', '长沙',
    '天津', '青岛', '宁波', '厦门'];

const MALE_JOBS = ['产品经理', '工程师', '设计师', '教师', '医生', '律师', '摄影师', '咖啡师', '健身教练',
    '项目经理', '会计', '运营', '销售', '建筑师', '编辑', '程序员'];

const FEMALE_JOBS = ['品牌设计师', '幼师', '摄影师', '护士', '编辑', '会计', '化妆师', '花艺师', '舞蹈老师',
    '运营', '翻译', '心理咨询师', '牙医', '主播', '秘书', '插画师'];

const MALE_BIOS = [
    '慢热但真诚，喜欢做饭和跑步。',
    '下班后的快乐是一杯咖啡和一部好电影。',
    '周末喜欢爬山，偶尔露营。',
    '做技术的，话不多，但认真。',
    '喜欢在城市的角落找好吃的小店。',
    '健身三年，也在学着好好生活。',
    '希望遇到可以一起分享日常的人。',
    '猫奴，做饭还行，脾气不错。',
];

const FEMALE_BIOS = [
    '喜欢逛展、拍照和在周末寻找好吃的面包店。',
    '想和你去看海，也想一起在家安静地听雨。',
    '城市漫游爱好者，擅长发现不起眼但很美的小角落。',
    '喜欢花，也喜欢把生活过成喜欢的样子。',
    '烘焙初学者，正在努力不把厨房炸掉。',
    '工作之外喜欢看书和听播客。',
    '希望遇到一个可以好好说话的人。',
    '爱笑，也爱安静。',
];

// ---------- 读取 .env ----------

function env_value(string $key, string $default = ''): string
{
    static $file = [];
    if ($file === []) {
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

$count = (int) ($argv[1] ?? 300);
$count = max(10, min(2000, $count));

// 第二个参数：统一口令。不传就随机（谁也登不进去）；传了就导出 CSV 给运营用
$plainPassword = (string) ($argv[2] ?? '');
$exportRows = [];
// 「男用户多一点」：60% 男 / 40% 女
$maleCount = (int) round($count * 0.6);
$femaleCount = $count - $maleCount;

$baseUrl = rtrim(env_value('APP_URL', 'https://clapi.cyjiaoyou.lol'), '/');
$avatarDir = __DIR__ . '/../server/storage/uploads/cl/fake';
$avatarUrl = $baseUrl . '/uploads/cl/fake';

// ---------- 1. 头像 ----------

if (! is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

/**
 * 下载一张头像（已有就跳过）。
 */
function ensurePortrait(string $dir, string $kind, int $index): bool
{
    $path = sprintf('%s/%s_%02d.jpg', $dir, $kind, $index);
    if (is_file($path) && filesize($path) > 1000) {
        return true;
    }

    // 优先用仓库里带的头像：服务器（阿里云香港）通常连不上 pravatar，也免得每次重新下载
    $bundled = sprintf('%s/assets/fake-avatars/%s_%02d.jpg', __DIR__, $kind, $index);
    if (is_file($bundled)) {
        copy($bundled, $path);
        @chmod($path, 0644);

        return true;
    }

    // 仓库里没有才联网下载
    $url = sprintf('https://i.pravatar.cc/400?img=%d', $index);
    $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20]]));
    if ($data === false || strlen($data) < 1000) {
        return false;
    }
    file_put_contents($path, $data);
    @chmod($path, 0644);

    return true;
}

echo "==> 准备头像\n";
$maleAvatars = [];
foreach (MALE_PORTRAITS as $i) {
    if (ensurePortrait($avatarDir, 'm', $i)) {
        $maleAvatars[] = sprintf('%s/m_%02d.jpg', $avatarUrl, $i);
    }
}
$femaleAvatars = [];
foreach (FEMALE_PORTRAITS as $i) {
    if (ensurePortrait($avatarDir, 'f', $i)) {
        $femaleAvatars[] = sprintf('%s/f_%02d.jpg', $avatarUrl, $i);
    }
}
printf("    男头像 %d 张 / 女头像 %d 张（目录 %s）\n", count($maleAvatars), count($femaleAvatars), $avatarDir);
if ($maleAvatars === [] || $femaleAvatars === []) {
    fwrite(STDERR, "头像下载失败，检查服务器能否访问 i.pravatar.cc\n");
    exit(1);
}

// ---------- 2. 清掉上一批 ----------

echo "==> 清理旧的演示账号\n";
$oldIds = $pdo->query("SELECT id FROM cl_user WHERE account LIKE '" . BOT_PREFIX . "%'")->fetchAll(PDO::FETCH_COLUMN);
if ($oldIds !== []) {
    $in = implode(',', array_fill(0, count($oldIds), '?'));
    foreach (['cl_coin_wallet', 'cl_point_wallet', 'cl_profile_audit', 'cl_user_photo'] as $table) {
        $pdo->prepare("DELETE FROM {$table} WHERE user_id IN ($in)")->execute($oldIds);
    }
    $pdo->prepare("DELETE FROM cl_user WHERE id IN ($in)")->execute($oldIds);
}
printf("    删除 %d 个旧账号\n", count($oldIds));

// ---------- 3. 造新账号 ----------

echo "==> 生成 {$count} 个演示账号（男 {$maleCount} / 女 {$femaleCount}）\n";

$insertUser = $pdo->prepare(
    'INSERT INTO cl_user (account, phone, password, gender, nickname, avatar, bio, birthday, city, height, job,
        education, dating_purpose, tags, level, real_name_status, status, source, show_in_nearby,
        show_in_recommend, last_active_at, created_at, updated_at)
     VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 1, ?, ?, NOW())',
);
$insertCoin = $pdo->prepare('INSERT INTO cl_coin_wallet (user_id, balance, created_at, updated_at) VALUES (?, 0, NOW(), NOW())');
$insertPoint = $pdo->prepare('INSERT INTO cl_point_wallet (user_id, balance, frozen, created_at, updated_at) VALUES (?, 0, 0, NOW(), NOW())');

$usedNames = [];
$now = time();

for ($i = 0; $i < $count; ++$i) {
    $male = $i < $maleCount;
    $gender = $male ? 1 : 2;

    // 昵称去重
    do {
        $nickname = SURNAMES[array_rand(SURNAMES)]
            . ($male ? MALE_GIVEN[array_rand(MALE_GIVEN)] : FEMALE_GIVEN[array_rand(FEMALE_GIVEN)]);
    } while (isset($usedNames[$nickname]));
    $usedNames[$nickname] = true;

    $avatar = $male
        ? $maleAvatars[array_rand($maleAvatars)]
        : $femaleAvatars[array_rand($femaleAvatars)];

    // 20~35 岁
    $ageDays = random_int(20 * 365, 35 * 365);
    // ⚠️ 必须 ×86400：time() 是秒，$ageDays 是天，直接减等于「几小时前」→ 所有人都是今天生日、0 岁
    $birthday = date('Y-m-d', $now - $ageDays * 86400);

    $account = BOT_PREFIX . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
    $passwordToUse = $plainPassword !== '' ? $plainPassword : bin2hex(random_bytes(16));

    $insertUser->execute([
        $account,
        password_hash($passwordToUse, PASSWORD_BCRYPT),
        $gender,
        $nickname,
        $avatar,
        $male ? MALE_BIOS[array_rand(MALE_BIOS)] : FEMALE_BIOS[array_rand(FEMALE_BIOS)],
        $birthday,
        $city = CITIES[array_rand(CITIES)],
        $male ? random_int(170, 188) : random_int(155, 172),
        $male ? MALE_JOBS[array_rand(MALE_JOBS)] : FEMALE_JOBS[array_rand(FEMALE_JOBS)],
        ['大专', '本科', '本科', '硕士'][array_rand(['大专', '本科', '本科', '硕士'])],
        ['认真交友', '长期关系', '兴趣搭子'][array_rand(['认真交友', '长期关系', '兴趣搭子'])],
        json_encode([1, 4, 7]),
        random_int(1, 3),
        // 60% 已实名，看起来更像真号
        random_int(1, 100) <= 60 ? 2 : 0,
        // 最近 10 分钟内活跃过 → 出现在「在线」
        date('Y-m-d H:i:s', $now - random_int(0, 600)),
        // 注册时间铺在最近 30 天
        date('Y-m-d H:i:s', $now - random_int(0, 30 * 86400)),
    ]);

    $id = (int) $pdo->lastInsertId();
    $insertCoin->execute([$id]);
    $insertPoint->execute([$id]);

    if ($plainPassword !== '') {
        $exportRows[] = [$account, $nickname, $male ? '男' : '女', $city ?? '', $plainPassword];
    }

    if (($i + 1) % 50 === 0) {
        echo "    ...{$i} 个\n";
    }
}

// ---------- 4. 收尾 ----------

@chmod($avatarDir, 0755);

echo "\n✅ 完成：{$maleCount} 男 / {$femaleCount} 女，账号前缀 " . BOT_PREFIX . "\n";

// 指定了口令就导出 CSV（运营拿去登录 App 用）
if ($plainPassword !== '' && $exportRows !== []) {
    $exportDir = __DIR__ . '/../server/storage/exports';
    if (! is_dir($exportDir)) {
        mkdir($exportDir, 0755, true);
    }
    $csvPath = sprintf('%s/bot-accounts-%s.csv', $exportDir, date('Ymd-His'));

    $fp = fopen($csvPath, 'w');
    fwrite($fp, "ï»¿"); // BOM，Excel 打开中文不乱码
    fputcsv($fp, ['账号', '昵称', '性别', '城市', '密码'], ',', '"', '');
    foreach ($exportRows as $row) {
        fputcsv($fp, $row, ',', '"', '');
    }
    fclose($fp);
    @chmod($csvPath, 0644);

    echo "   账号密码已导出：{$csvPath}
";
    echo "   宝塔文件管理器里下载，或者 scp 拉走。
";
    echo "   ⚠️ 这批号能用同一个口令登录 App；演示结束后重跑一次不带口令的版本即可重新随机化。
";
}

echo "   清理命令：DELETE FROM cl_user WHERE account LIKE '" . BOT_PREFIX . "%';\n";
