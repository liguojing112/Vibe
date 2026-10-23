<?php

declare(strict_types=1);

/**
 * 系统消息演示数据（客户 2026-10-23 要求：系统消息里要有历史记录，从 2018 年到现在）。
 *
 * 在服务器上跑（PHP + PDO，不依赖框架）：
 *
 *   cd /www/wwwroot/cljy/server
 *   php ../scripts/seed-fake-notices.php              # 100 条全员公告
 *   php ../scripts/seed-fake-notices.php 60           # 自定义条数
 *
 * 说明：
 *   - 插的是**全员公告**（`user_id = 0`）：所有用户都能看到，包括脚本跑完之后才注册的新账号；
 *   - 已读状态按用户存在 `cl_notice_read`，脚本会给现有用户补记录（最后两条留未读）；
 *   - `biz_type` 统一 `ANNOUNCE`，和后台群发公告一致；
 *   - 时间从 2018-01 均匀铺到现在并加随机抖动，看起来像真实运营历史；
 *   - 最后 2 条故意不置已读，进页面能看到未读角标，点进去就消失；
 *   - **可重复执行**：每次先删掉本脚本造的 `ANNOUNCE` 通知再插，不会越跑越多。
 */

date_default_timezone_set('Asia/Shanghai');

const DEFAULT_COUNT = 100;
const UNREAD_TAIL = 2;

/** 2018~2020：平台起步期。 */
const EARLY_TEMPLATES = [
    ['「畅撩交友」正式上线', '感谢你成为我们最早的一批用户。从今天起，你可以在这里遇见同频的人，聊聊今天的心情。'],
    ['注册用户突破 10 万', '上线第 3 个月，我们迎来了第 10 万位用户。谢谢每一个认真聊天的人。'],
    ['新增「兴趣标签」', '现在可以在资料里选择最多 5 个兴趣标签，推荐页会优先展示和你标签重合的人。'],
    ['春节不打烊', '春节期间平台正常运营，客服值班时间为每天 10:00 - 22:00。祝你新春快乐。'],
    ['隐私政策更新', '为符合最新规范，我们更新了《隐私政策》，主要调整了信息收集范围的说明。'],
    ['新增「同城」Tab', '发现页新增「同城」分类，可以看到同一城市最近活跃的人。'],
    ['真人认证功能上线', '完成真人认证后，你的主页会展示认证标识，更容易被信任。'],
    ['头像审核规则调整', '为了社区环境，头像将统一进行人工审核，通常 24 小时内完成。'],
    ['服务器升级公告', '我们将于本周日凌晨 2:00-4:00 进行服务器升级，期间可能短暂无法访问。'],
    ['用户突破 100 万', '我们突破了一百万用户。为了更好的体验，推荐算法也做了一次全面优化。'],
    ['新增「最近访客」', '现在可以看到最近浏览过你主页的人，在消息页的「最近访客」入口。'],
    ['客服邮箱变更', '原客服邮箱停止使用，如有问题请在「我的 - 帮助与反馈」中提交。'],
];

/** 2021~2023：功能完善期。 */
const MID_TEMPLATES = [
    ['金币系统上线', '私信、礼物等功能统一使用金币结算。金币仅可通过平台客服充值，无其他渠道。'],
    ['礼物功能上线', '现在可以给喜欢的人送礼物了。礼物会展示在对方的互动通知里。'],
    ['会员服务上线', '开通会员可享受更多私信额度和专属标识，具体权益见「我的 - 会员中心」。'],
    ['安全中心上线', '新增安全中心，可以查看登录设备、修改密码、管理黑名单。'],
    ['App 2.0 发布', '全新界面、更快加载速度。建议在 Wi-Fi 环境下更新。'],
    ['语音通话内测', '语音通话功能开始小范围内测，可在聊天页工具栏体验。'],
    ['收益提现规则调整', '为保障资金安全，提现统一由客服人工处理，工作日 24 小时内到账。'],
    ['社区公约发布', '我们发布了《社区公约》，明确了平台鼓励和不鼓励的行为，请查阅。'],
    ['违规账号处理公示', '本期共处理违规账号 1,263 个，主要涉及虚假资料和骚扰行为。'],
    ['视频通话上线', '视频通话正式上线，可在对方主页或聊天页发起。'],
    ['等级体系上线', '根据活跃度和互动情况，你将获得等级和对应权益，等级每月更新。'],
    ['反诈提醒', '平台不会以任何理由索要验证码或要求转账，遇到可疑情况请立即举报。'],
];

/** 2024~2026：稳定运营期。 */
const LATE_TEMPLATES = [
    ['推荐算法优化完成', '本次优化提升了同城和兴趣匹配的准确度，你可能会看到一些新面孔。'],
    ['会员权益升级', '会员新增「优先展示」权益，在推荐页的曝光权重会有所提升。'],
    ['账号安全提醒', '检测到你的账号在新设备登录。如非本人操作，请立即修改密码。'],
    ['春节活动公告', '春节期间赠送礼物可获得双倍互动值，活动时间以站内公告为准。'],
    ['实名认证要求更新', '根据监管要求，提现前需完成实名认证，认证信息仅用于身份核验。'],
    ['深夜陪伴计划', '我们上线了深夜陪伴专题，22:00 后推荐更偏向在线活跃的用户。'],
    ['数据安全升级', '本次升级加强了敏感信息的加密存储，你的资料安全等级进一步提高。'],
    ['客服响应提速', '客服平均响应时间缩短至 5 分钟以内，高峰期可能略有延迟。'],
    ['系统维护完成', '本次维护已完成，所有功能恢复正常。给你带来的不便请见谅。'],
    ['隐私保护升级', '新增「隐藏在线状态」开关，可在隐私设置中开启。'],
    ['举报处理结果', '你提交的举报已处理完成，感谢你为社区环境做出的贡献。'],
    ['资料审核通过', '你提交的资料已通过审核，现在会展示在个人主页上。'],
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

$count = max(10, min(500, (int) ($argv[1] ?? DEFAULT_COUNT)));

// 全员公告：只插一份（user_id = 0），所有用户 —— 包括以后新注册的 —— 都能看到。
// 公告的已读状态按用户存在 cl_notice_read，所以通知行上不写 read_at。
$insert = $pdo->prepare(
    'INSERT INTO cl_notice (user_id, category, title, content, biz_type, biz_id, read_at, created_at, updated_at)
     VALUES (0, 1, ?, ?, ?, NULL, NULL, ?, ?)',
);
$insertRead = $pdo->prepare(
    'INSERT IGNORE INTO cl_notice_read (notice_id, user_id, created_at, updated_at) VALUES (?, ?, ?, ?)',
);

$start = strtotime('2018-01-05 10:00:00');
$end = time() - 3600;
$step = (int) round(($end - $start) / max(1, $count - 1));

$pdo->beginTransaction();
try {
    // 可重复执行：清掉上次造的公告（连同已读记录），真实业务通知不碰
    $pdo->exec("DELETE FROM cl_notice_read WHERE notice_id IN (SELECT id FROM cl_notice WHERE biz_type = 'ANNOUNCE')");
    $pdo->exec("DELETE FROM cl_notice WHERE biz_type = 'ANNOUNCE'");

    $noticeIds = [];
    for ($i = 0; $i < $count; ++$i) {
        // 按进度分三档：早期 / 中期 / 近期，内容跟年代对得上
        $ratio = $count > 1 ? $i / ($count - 1) : 1.0;
        $pool = $ratio < 0.3 ? EARLY_TEMPLATES : ($ratio < 0.7 ? MID_TEMPLATES : LATE_TEMPLATES);
        [$title, $content] = $pool[random_int(0, count($pool) - 1)];

        $createdAt = $start + $i * $step + random_int(-3, 3) * 86400;
        $createdAt = min($end, max($start, $createdAt));
        $stamp = date('Y-m-d H:i:s', $createdAt);

        $insert->execute([$title, $content, 'ANNOUNCE', $stamp, $stamp]);
        $noticeIds[] = (int) $pdo->lastInsertId();
    }

    // 现有用户：除最后两条外都补一条已读记录，否则一进 App 红点就是 100
    $userIds = array_map(
        static fn (array $row): int => (int) $row['id'],
        $pdo->query("SELECT id FROM cl_user WHERE account NOT LIKE 'bot%'")->fetchAll(PDO::FETCH_ASSOC),
    );
    $readIds = array_slice($noticeIds, 0, max(0, count($noticeIds) - UNREAD_TAIL));
    $now = date('Y-m-d H:i:s');
    foreach ($userIds as $userId) {
        foreach ($readIds as $noticeId) {
            $insertRead->execute([$noticeId, $userId, $now, $now]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, '插入失败：' . $e->getMessage() . "\n");
    exit(1);
}

printf(
    "完成：%d 条全员公告（%s ~ %s），已为 %d 个现有用户补齐已读\n",
    count($noticeIds),
    date('Y-m-d', $start),
    date('Y-m-d', $end),
    count($userIds),
);
