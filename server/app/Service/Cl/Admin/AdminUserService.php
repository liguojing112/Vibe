<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminUserRepository;
use App\Repository\Cl\CoinLogRepository;
use App\Repository\Cl\PointLogRepository;
use App\Repository\Cl\RealNameRepository;
use App\Repository\Cl\UserPhotoRepository;
use App\Repository\Cl\UserRepository;
use App\Repository\Cl\VipOrderRepository;
use App\Repository\Cl\VipPlanRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Notice\NoticeService;
use App\Service\Cl\Vip\VipService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Carbon\Carbon;

/**
 * 后台用户管理（docs/07 第 3.2 节）。
 *
 * 三条自己定的规矩，写在前面：
 * 1. **改用户状态的每个动作都要留一条 `cl_admin_action_log`**（封禁/调级/开通会员/改性别…），
 *    因为 `cl_user` 上只有当前值，事后要能说清楚「谁在什么时候把它从什么改成了什么」；
 * 2. **封禁/解封要发通知**（用户下次打开 App 能看见原因，docs/06 场景 12）；
 * 3. 名字、头像这类**绕过审核**的改动只动 `cl_user` 上「已通过」的字段，
 *    不去碰用户的待审资料（那是审核中心的事，docs/07 第 3.4 节）。
 */
final class AdminUserService
{
    use RetriesTransactions;

    /** 详情抽屉里「最近流水」取多少条（docs/07 第 2 节：最近 20 条）。 */
    private const RECENT_LOGS = 20;

    /** 导出一次最多多少行，别把内存和浏览器拖死。 */
    private const EXPORT_LIMIT = 5000;

    public function __construct(
        private readonly AdminUserRepository $repository,
        private readonly UserRepository $userRepository,
        private readonly UserPhotoRepository $userPhotoRepository,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
        private readonly RealNameRepository $realNameRepository,
        private readonly CoinLogRepository $coinLogRepository,
        private readonly PointLogRepository $pointLogRepository,
        private readonly VipPlanRepository $vipPlanRepository,
        private readonly VipOrderRepository $vipOrderRepository,
        private readonly NoticeService $noticeService,
    ) {}

    // ---------- 读 ----------

    public function list(array $filters, int $page, int $pageSize): array
    {
        $result = $this->repository->pageList($filters, $page, $pageSize);
        $result['list'] = $this->withWalletBalances($result['list']);

        return $result;
    }

    /** 用户详情（抽屉）。字段名和后台的 `ClUserDetailVo` 对齐。 */
    public function detail(int $id): array
    {
        $user = $this->mustFind($id);
        $coin = $this->coinWalletService->summaryOfMany([$id])[$id] ?? null;
        $point = $this->pointWalletService->summaryOfMany([$id])[$id] ?? null;
        $realName = $this->realNameRepository->findByUserId($id);

        return [
            'user' => [
                'id' => (int) $user->id,
                'account' => (string) $user->account,
                'phone' => (string) ($user->phone ?? ''),
                'nickname' => (string) $user->nickname,
                'avatar' => (string) $user->avatar,
                'gender' => (int) $user->gender,
                'city' => (string) $user->city,
                'birthday' => $user->birthday?->format('Y-m-d'),
                'height' => $user->height === null ? null : (int) $user->height,
                'job' => (string) $user->job,
                'education' => (string) $user->education,
                'bio' => (string) $user->bio,
                'status' => (int) $user->status,
                'source' => (int) $user->source,
                'batchNo' => (string) ($user->batch_no ?? ''),
                'createdAt' => $user->created_at?->toDateTimeString(),
                'lastActiveAt' => $user->last_active_at?->toDateTimeString(),
            ],
            'photos' => $this->photosOf($id),
            'coin' => [
                'balance' => $coin['balance'] ?? 0,
                'totalRecharge' => $coin['totalRecharge'] ?? 0,
            ],
            'point' => [
                'balance' => $point['balance'] ?? 0,
                'frozen' => $point['frozen'] ?? 0,
                'totalEarned' => (int) $user->total_earned_points,
            ],
            'level' => ['level' => (int) $user->level, 'locked' => (int) $user->level_locked === 1],
            'realName' => [
                'status' => (int) $user->real_name_status,
                'realName' => $realName?->real_name,
                'idCardMask' => $realName?->id_card_mask,
            ],
            'vip' => ['expireAt' => $user->isVip() ? $user->vip_expire_at?->toDateTimeString() : null],
            'bans' => $this->banHistoryOf($id),
            'logs' => $this->recentLogsOf($id),
        ];
    }

    // ---------- 写 ----------

    /** 直接改昵称/头像/简介（绕过审核，docs/07 第 3.2 节）。 */
    public function updateProfile(AdminUser $operator, int $id, array $data): void
    {
        $user = $this->mustFind($id);
        $fields = array_filter(
            ['nickname' => $data['nickname'] ?? null, 'avatar' => $data['avatar'] ?? null, 'bio' => $data['bio'] ?? null],
            static fn ($value) => $value !== null && $value !== '',
        );
        if ($fields === []) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '没有要改的内容');
        }

        $this->userRepository->saveById($id, $fields);
        $this->log($operator, $id, AdminActionLog::ACTION_PROFILE, $this->describeChanges($user, $fields));
    }

    /** 重置密码：返回**明文**，只在这一次响应里出现（docs/07 第 3.2 节）。 */
    public function resetPassword(AdminUser $operator, int $id): array
    {
        $this->mustFind($id);
        $plain = $this->randomPassword();

        $this->userRepository->saveById($id, ['password' => password_hash($plain, PASSWORD_BCRYPT)]);
        $this->log($operator, $id, AdminActionLog::ACTION_PASSWORD, '重置了登录密码');

        return ['password' => $plain];
    }

    /**
     * 封禁。`$days = 0` 表示永久。
     *
     * 顺带把「登录失败次数/锁定」清掉 —— 封禁和解锁是两回事，不清的话解封后
     * 用户可能还卡在「密码错误 5 次锁 15 分钟」里。
     */
    public function ban(AdminUser $operator, int $id, string $reason, int $days): void
    {
        $user = $this->mustFind($id);
        if ($user->status === User::STATUS_CANCELLED) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '已注销的账号不能封禁');
        }

        $until = $days > 0 ? Carbon::now()->addDays($days) : null;

        $this->runTransaction(function () use ($user, $reason, $days, $until, $operator): void {
            $this->userRepository->saveById($user->id, [
                'status' => User::STATUS_BANNED,
                'ban_reason' => $reason,
                'ban_until' => $until?->toDateTimeString(),
                'login_fail_count' => 0,
                'locked_until' => null,
            ]);

            $this->log(
                $operator,
                $user->id,
                AdminActionLog::ACTION_BAN,
                $days > 0 ? sprintf('封禁 %d 天：%s', $days, $reason) : '永久封禁：' . $reason,
            );

            $this->noticeService->banned($user->id, $reason, $days > 0 ? $days : null);
        });
    }

    public function unban(AdminUser $operator, int $id, string $reason): void
    {
        $user = $this->mustFind($id);
        if ($user->status !== User::STATUS_BANNED) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '这个账号当前不是封禁状态');
        }

        $this->runTransaction(function () use ($user, $reason, $operator): void {
            $this->userRepository->saveById($user->id, [
                'status' => User::STATUS_NORMAL,
                'ban_reason' => null,
                'ban_until' => null,
            ]);

            $this->log($operator, $user->id, AdminActionLog::ACTION_UNBAN, '解封：' . $reason);
        });
    }

    /** 调整等级（只对女用户有意义，docs/06 第 5 节）。 */
    public function setLevel(AdminUser $operator, int $id, int $level, bool $locked, string $reason): void
    {
        $user = $this->mustFind($id);
        if ($user->gender !== User::GENDER_FEMALE) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '等级只对女用户有意义');
        }
        if ($level < 1 || $level > 5) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '等级只能是 1~5');
        }

        $from = (int) $user->level;
        $this->userRepository->saveById($id, ['level' => $level, 'level_locked' => $locked ? 1 : 0]);
        $this->log(
            $operator,
            $id,
            AdminActionLog::ACTION_LEVEL,
            sprintf('等级 Lv%d → Lv%d%s，原因：%s', $from, $level, $locked ? '（并锁定）' : '', $reason),
        );
    }

    /**
     * 后台开通/延长会员（不扣金币）。
     *
     * 到期时间的算法和 App 买会员**完全一致**（未过期在原到期时间上加、已过期从现在算），
     * 所以抽在 [VipService::extendExpire] 里两边共用 —— 各写一份迟早会走岔。
     */
    public function grantVip(AdminUser $operator, int $id, ?int $planId, int $days, string $remark): void
    {
        $user = $this->mustFind($id);
        if ($user->gender !== User::GENDER_MALE) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会员只对男用户有意义');
        }

        $plan = $planId === null ? null : $this->vipPlanRepository->findById($planId);
        if ($planId !== null && $plan === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '套餐不存在');
        }

        $openDays = $plan === null ? $days : (int) $plan->days;
        if ($openDays <= 0) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '开通天数必须大于 0');
        }

        $this->runTransaction(function () use ($user, $plan, $openDays, $remark, $operator): void {
            $expireAfter = VipService::extendExpire($user->vip_expire_at, $openDays);
            $expireBefore = $user->vip_expire_at !== null && $user->vip_expire_at->isFuture()
                ? $user->vip_expire_at
                : null;

            $this->vipOrderRepository->create([
                'order_no' => 'admin-' . bin2hex(random_bytes(12)),
                'user_id' => $user->id,
                'plan_id' => $plan?->id,
                'days' => $openDays,
                'price_coins' => 0,
                'source' => 2,
                'expire_before' => $expireBefore?->toDateTimeString(),
                'expire_after' => $expireAfter->toDateTimeString(),
                'operator_id' => $operator->id,
                'remark' => $remark,
            ]);

            $this->userRepository->saveById($user->id, ['vip_expire_at' => $expireAfter->toDateTimeString()]);

            $this->log(
                $operator,
                $user->id,
                AdminActionLog::ACTION_VIP,
                sprintf(
                    '后台开通会员 %d 天%s，到期 %s%s',
                    $openDays,
                    $plan === null ? '' : '（' . $plan->name . '）',
                    $expireAfter->format('Y-m-d'),
                    $remark === '' ? '' : '，备注：' . $remark,
                ),
            );
        });
    }

    /** 改性别（超管专有，docs/07 第 3.2 节）。 */
    public function changeGender(AdminUser $operator, int $id, int $gender, string $reason): void
    {
        $user = $this->mustFind($id);
        if (! in_array($gender, [User::GENDER_MALE, User::GENDER_FEMALE], true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '性别只能是男或女');
        }
        if ((int) $user->gender === $gender) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '性别没有变化');
        }

        $this->userRepository->saveById($id, ['gender' => $gender]);
        $this->log(
            $operator,
            $id,
            AdminActionLog::ACTION_GENDER,
            sprintf('性别 %s → %s，原因：%s', $this->genderText((int) $user->gender), $this->genderText($gender), $reason),
        );
    }

    // ---------- 导出 ----------

    /**
     * 导出当前筛选结果（docs/07 第 3.2 节）。
     *
     * **不导出密码和身份证** —— 表头里压根没有这两列，不是靠调用方记得删。
     *
     * @return array{headers: string[], rows: array<int,array<int,string>>}
     */
    public function exportRows(array $filters, bool $female): array
    {
        $headers = ['ID', '账号', '手机号', '昵称', '城市', '状态', '注册来源', '批次号', '注册时间', '最后活跃'];
        $headers = $female
            ? [...$headers, '等级', '积分余额', '冻结积分', '累计收益', '实名状态']
            : [...$headers, '金币余额', '累计充值', '会员到期'];

        $rows = [];
        foreach ($this->withWalletBalances($this->repository->exportRows($filters, self::EXPORT_LIMIT)) as $row) {
            $rows[] = $female ? $this->femaleRow($row) : $this->maleRow($row);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * 把钱包余额批量补进每一行。
     *
     * 走的是钱包服务而不是在 SQL 里 join 钱包表 —— `scripts/check-money.sh` 只允许
     * `CoinWalletService` / `PointWalletService` 碰钱包，读也一样。
     * 整页一次查完，不是 N+1。
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function withWalletBalances(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $coins = $this->coinWalletService->summaryOfMany($ids);
        $points = $this->pointWalletService->summaryOfMany($ids);

        return array_map(static function (array $row) use ($coins, $points): array {
            $id = (int) $row['id'];

            return [
                ...$row,
                'coin_balance' => $coins[$id]['balance'] ?? 0,
                'coin_total_recharge' => $coins[$id]['totalRecharge'] ?? 0,
                'point_balance' => $points[$id]['balance'] ?? 0,
                'point_frozen' => $points[$id]['frozen'] ?? 0,
            ];
        }, $rows);
    }

    // ---------- 内部 ----------

    private function mustFind(int $id): User
    {
        $user = $this->userRepository->findById($id);
        if (! $user instanceof User) {
            throw new ClBusinessException(BizCode::USER_NOT_FOUND);
        }

        return $user;
    }

    private function log(AdminUser $operator, int $userId, string $action, string $detail): void
    {
        AdminActionLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'detail' => mb_substr($detail, 0, 255),
            'operator_id' => $operator->id,
            'operator_name' => (string) $operator->username,
        ]);
    }

    /** 「昵称：张三 → 李四」这样的前后值，给日志用。 */
    private function describeChanges(User $before, array $fields): string
    {
        $labels = ['nickname' => '昵称', 'avatar' => '头像', 'bio' => '简介'];
        $parts = [];
        foreach ($fields as $field => $after) {
            $parts[] = sprintf('%s：%s → %s', $labels[$field], $this->shorten((string) $before->{$field}), $this->shorten((string) $after));
        }

        return implode('；', $parts);
    }

    private function shorten(string $value): string
    {
        return mb_strlen($value) > 20 ? mb_substr($value, 0, 20) . '…' : $value;
    }

    private function randomPassword(): string
    {
        // 只从「看得清」的字符里取：后台要把这串念给用户或贴给他，0/O、1/l 这类容易看错
        $pool = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 10; ++$i) {
            $password .= $pool[random_int(0, strlen($pool) - 1)];
        }

        return $password;
    }

    private function genderText(int $gender): string
    {
        return $gender === User::GENDER_MALE ? '男' : '女';
    }

    /** @return array<int,array{reason: string, bannedAt: null|string, bannedUntil: null|string}> */
    private function banHistoryOf(int $userId): array
    {
        $rows = AdminActionLog::query()
            ->where('user_id', $userId)
            ->whereIn('action', [AdminActionLog::ACTION_BAN, AdminActionLog::ACTION_UNBAN])
            ->orderByDesc('id')
            ->get();

        $list = [];
        foreach ($rows as $row) {
            $list[] = [
                'reason' => (string) $row->detail,
                'bannedAt' => $row->created_at?->toDateTimeString(),
                'bannedUntil' => $row->action === AdminActionLog::ACTION_BAN
                    ? $this->mustFind($userId)->ban_until?->toDateTimeString()
                    : null,
                'operator' => (string) $row->operator_name,
            ];
        }

        return $list;
    }

    /** 金币流水和积分流水合在一起按时间倒序取前 20 条（抽屉里只有一栏）。 */
    private function recentLogsOf(int $userId): array
    {
        $coins = $this->coinLogRepository->pageForUser($userId, [], 0, self::RECENT_LOGS);
        $points = $this->pointLogRepository->pageForUser($userId, 0, self::RECENT_LOGS);

        $merged = [];
        foreach ($coins as $row) {
            $merged[] = [...$row, 'type' => 'coin'];
        }
        foreach ($points as $row) {
            $merged[] = [...$row, 'type' => 'point'];
        }

        usort($merged, static fn (array $a, array $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return array_slice($merged, 0, self::RECENT_LOGS);
    }

    /** 抽屉里的相册：只列**审核通过**的那些（就是别人在 App 上看到的相册）。 */
    private function photosOf(int $userId): array
    {
        $urls = [];
        foreach ($this->userPhotoRepository->listByUser($userId) as $photo) {
            if ((int) $photo->audit_status === UserPhoto::AUDIT_APPROVED) {
                $urls[] = (string) $photo->url;
            }
        }

        return $urls;
    }

    private function maleRow(array $row): array
    {
        return [
            (string) $row['id'], (string) $row['account'], (string) ($row['phone'] ?? ''), (string) $row['nickname'],
            (string) $row['city'], $this->statusText((int) $row['status']), $this->sourceText((int) $row['source']),
            (string) ($row['batch_no'] ?? ''), (string) $row['created_at'], (string) ($row['last_active_at'] ?? ''),
            '', '', '', '',
            '', (string) (int) $row['coin_balance'], (string) (int) $row['coin_total_recharge'],
            (string) ($row['vip_expire_at'] ?? ''),
        ];
    }

    private function femaleRow(array $row): array
    {
        return [
            (string) $row['id'], (string) $row['account'], (string) ($row['phone'] ?? ''), (string) $row['nickname'],
            (string) $row['city'], $this->statusText((int) $row['status']), $this->sourceText((int) $row['source']),
            (string) ($row['batch_no'] ?? ''), (string) $row['created_at'], (string) ($row['last_active_at'] ?? ''),
            'Lv' . (int) $row['level'], (string) (int) $row['point_balance'], (string) (int) $row['point_frozen'],
            (string) (int) $row['total_earned_points'], $this->realNameText((int) $row['real_name_status']),
            '', '', '',
        ];
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            User::STATUS_BANNED => '已封禁',
            User::STATUS_CANCELLED => '已注销',
            default => '正常',
        };
    }

    private function sourceText(int $source): string
    {
        return $source === 2 ? '后台开号' : '自主注册';
    }

    private function realNameText(int $status): string
    {
        return match ($status) {
            User::REAL_NAME_AUDITING => '审核中',
            User::REAL_NAME_PASSED => '已通过',
            User::REAL_NAME_REJECTED => '已驳回',
            default => '未认证',
        };
    }
}
