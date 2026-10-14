<?php

declare(strict_types=1);

namespace App\Service\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Repository\Cl\Admin\AdminUserRepository;
use App\Repository\Cl\Admin\BatchAccountRepository;
use App\Service\Cl\Chat\Concerns\RetriesTransactions;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;

/**
 * 后台批量开号（docs/07 第 3.3 节、docs/04 第 7 节）。
 *
 * 三条硬规则：
 * 1. **明文密码只出现一次**：生成时随响应返回，服务端**一个字节都不存**（库里只有 bcrypt），
 *    前端当场下载完就没了 —— 关掉弹窗只能对单个账号重置密码；
 * 2. **账号 = 前缀 + 8 位数字**，从一个前缀的「已用最大号 + 1」往下发，
 *    所以唯一性是算出来的，不是随机撞出来的；
 * 3. **批量号不带头像**（`avatar` 留空）：`DiscoverRepository` 会过滤掉 `avatar = ''` 的人，
 *    也就是说这批号在本人登录上传并过审之前，**不会出现在发现页**。
 */
final class AdminBatchService
{
    use RetriesTransactions;

    /** 一次最多开多少号（docs/07 第 3.3 节：1~200）。 */
    public const MAX_COUNT = 200;

    /** 账号数字部分从哪个号起发（8 位，所以是 10000001）。 */
    private const FIRST_NUMBER = 10000001;

    private const LAST_NUMBER = 99999999;

    /** 密码长度（docs/04 第 7 节：随机 8 位，大小写字母 + 数字）。 */
    private const PASSWORD_LENGTH = 8;

    private const PASSWORD_POOL = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private readonly AdminUserRepository $userRepository,
        private readonly BatchAccountRepository $batchRepository,
        private readonly CoinWalletService $coinWalletService,
        private readonly PointWalletService $pointWalletService,
    ) {}

    /**
     * 开一批号。
     *
     * @return array{batch_no: string, gender: int, count: int, accounts: array<int,array{account: string, nickname: string, password: string}>}
     */
    public function create(AdminUser $operator, int $gender, int $count, string $prefix, string $remark): array
    {
        if (! in_array($gender, [User::GENDER_MALE, User::GENDER_FEMALE], true)) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '性别只能选男或女');
        }
        if ($count < 1 || $count > self::MAX_COUNT) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '一次最多开 ' . self::MAX_COUNT . ' 个号');
        }

        $prefix = $this->normalizePrefix($prefix, $gender);
        $start = max(self::FIRST_NUMBER, $this->userRepository->maxAccountNumber($prefix) + 1);
        if ($start + $count - 1 > self::LAST_NUMBER) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '前缀 ' . $prefix . ' 的号段用完了，换一个前缀');
        }

        $batchNo = $this->makeBatchNo();

        $accounts = $this->runTransaction(function () use ($operator, $gender, $count, $prefix, $remark, $batchNo, $start): array {
            $this->batchRepository->createBatch([
                'batch_no' => $batchNo,
                'gender' => $gender,
                'count' => $count,
                'account_prefix' => $prefix,
                'operator_id' => $operator->id,
                'remark' => $remark,
                'exported' => 0,
            ]);

            $created = [];
            for ($i = 0; $i < $count; ++$i) {
                $account = $prefix . str_pad((string) ($start + $i), 8, '0', STR_PAD_LEFT);
                $password = $this->randomPassword();

                /** @var User $user */
                $user = User::query()->create([
                    'account' => $account,
                    'phone' => null,
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'gender' => $gender,
                    // 昵称先给个能认出来的，本人登录后再自己改（docs/04 第 7 节）
                    'nickname' => '用户' . substr($account, -4),
                    'avatar' => '',
                    'city' => '',
                    'level' => 1,
                    'status' => User::STATUS_NORMAL,
                    'source' => 2,
                    'batch_no' => $batchNo,
                ]);

                // 钱包要跟着建：没有钱包的号收不了付费私信 / 送不出礼物（注册接口也会建）
                $this->coinWalletService->createForUser($user->id);
                $this->pointWalletService->createForUser($user->id);

                $created[] = [
                    'account' => $account,
                    'nickname' => (string) $user->nickname,
                    'password' => $password,
                ];
            }

            return $created;
        });

        return [
            'batch_no' => $batchNo,
            'gender' => $gender,
            'count' => $count,
            'accounts' => $accounts,
        ];
    }

    /**
     * 批次列表（docs/07 第 3.3 节）。
     *
     * @return array{list: array<int,array<string,mixed>>, total: int}
     */
    public function batches(int $page, int $pageSize): array
    {
        $result = $this->batchRepository->pageList($page, $pageSize);

        $names = $this->operatorNames(array_column($result['list'], 'operator_id'));
        foreach ($result['list'] as &$row) {
            $row['operator_name'] = $names[$row['operator_id']] ?? '—';
        }
        unset($row);

        return $result;
    }

    /**
     * 标记「这批的明文已经导出过了」。
     *
     * 只做标记、**不返回密码** —— 明文生成时就随响应给出去、服务端不存，
     * 所以这里没法再给一次。这条对应 docs/04 第 7 节的 `exported`，
     * 也就是批次列表里那一列「是否已导出」。
     */
    public function markExported(string $batchNo): void
    {
        $batch = $this->batchRepository->findByBatchNo($batchNo);
        if ($batch === null) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '批次不存在');
        }

        $this->batchRepository->markExported((int) $batch->id);
    }

    // ---------- 内部 ----------

    private function normalizePrefix(string $prefix, int $gender): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            $prefix = $gender === User::GENDER_FEMALE ? 'F' : 'M';
        }
        if (preg_match('/^[A-Za-z0-9]{1,8}$/', $prefix) !== 1) {
            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '账号前缀只能是 1~8 位字母或数字');
        }

        return strtoupper($prefix);
    }

    /** 批次号：日期 + 随机尾巴（`batch_no` 上有唯一索引，撞了就是插入失败）。 */
    private function makeBatchNo(): string
    {
        return 'B' . date('YmdHis') . strtoupper(bin2hex(random_bytes(2)));
    }

    private function randomPassword(): string
    {
        $password = '';
        for ($i = 0; $i < self::PASSWORD_LENGTH; ++$i) {
            $password .= self::PASSWORD_POOL[random_int(0, strlen(self::PASSWORD_POOL) - 1)];
        }

        return $password;
    }

    /**
     * @param array<int,null|int> $ids
     * @return array<int,string>
     */
    private function operatorNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $map = [];
        foreach (AdminUser::query()->whereIn('id', $ids)->get(['id', 'username']) as $user) {
            $map[(int) $user->id] = (string) $user->username;
        }

        return $map;
    }
}
