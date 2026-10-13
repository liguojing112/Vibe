<?php

declare(strict_types=1);

namespace App\Service\Cl\Auth;

use App\Exception\ClBusinessException;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Lcobucci\JWT\Token\RegisteredClaims;
use Lcobucci\JWT\UnencryptedToken;
use Mine\Jwt\Factory;
use Mine\Jwt\JwtInterface;

/**
 * App 用户认证（docs/02 第 2.4 节、docs/05 第 2 与 4.1 节）。
 *
 * - JWT 用独立的 scene `api`，密钥是 .env 的 CL_APP_JWT_SECRET，与后台不同，
 *   因此后台 token 打不开 App 接口，App token 也打不开后台接口。
 * - 连续输错密码 MAX_LOGIN_FAIL 次锁 LOCK_MINUTES 分钟。
 * - 登录按 IP 限流。
 */
class AppAuthService
{
    public const JWT_SCENE = 'api';

    public const MAX_LOGIN_FAIL = 5;

    public const LOCK_MINUTES = 15;

    public const LOGIN_RATE_PER_MINUTE = 10;

    public function __construct(
        protected readonly UserRepository $userRepository,
        protected readonly CoinWalletService $coinWalletService,
        protected readonly PointWalletService $pointWalletService,
        protected readonly Factory $jwtFactory,
        protected readonly Redis $redis,
    ) {}

    public function jwt(): JwtInterface
    {
        return $this->jwtFactory->get(self::JWT_SCENE);
    }

    /**
     * 注册（docs/01 P00）：手机号 + 密码 + 性别 + 昵称，注册后按性别建钱包。
     *
     * @return array<string,int|string>
     */
    public function register(string $phone, string $password, int $gender, string $nickname): array
    {
        if ($this->userRepository->findByPhone($phone) !== null) {
            throw new ClBusinessException(BizCode::ACCOUNT_EXISTS);
        }

        try {
            $user = Db::transaction(function () use ($phone, $password, $gender, $nickname): User {
                $user = User::create([
                    'account' => $phone,
                    'phone' => $phone,
                    'password' => password_hash($password, \PASSWORD_BCRYPT),
                    'gender' => $gender,
                    'nickname' => $nickname,
                    'source' => 1,
                ]);
                // 钱包只能通过钱包服务创建（scripts/check-money.sh 会扫描）
                if ($gender === User::GENDER_MALE) {
                    $this->coinWalletService->createForUser($user->id);
                } else {
                    $this->pointWalletService->createForUser($user->id);
                }
                return $user;
            });
        } catch (UniqueConstraintViolationException $e) {
            // 并发注册同一手机号时靠唯一索引兜底
            if (User::query()->where('phone', $phone)->exists()) {
                throw new ClBusinessException(BizCode::ACCOUNT_EXISTS);
            }
            throw $e;
        }

        return $this->issueTokens($user);
    }

    /**
     * 登录：账号或手机号 + 密码。
     *
     * @return array<string,int|string>
     */
    public function login(string $account, string $password, string $ip, string $deviceId = ''): array
    {
        $this->assertLoginRate($ip);

        $user = $this->userRepository->findByAccountOrPhone($account);
        if ($user === null) {
            throw new ClBusinessException(BizCode::LOGIN_FAILED);
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $minutes = max(1, (int) ceil(Carbon::now()->diffInSeconds($user->locked_until) / 60));
            throw new ClBusinessException(BizCode::ACCOUNT_LOCKED, "密码错误次数过多，账号已锁定，请 {$minutes} 分钟后再试");
        }

        if (! $user->verifyPassword($password)) {
            $this->recordLoginFail($user);
            throw new ClBusinessException(BizCode::LOGIN_FAILED);
        }

        $this->assertNotBanned($user);

        // 注销过的账号也登不进来。不加这一条的话：登录能成功，但拿到 token 之后
        // `findActiveUser` 会因为它已注销而返回 null → 每个接口都 401，
        // 用户只会看到「登录已失效」的循环（真跑出来过）。
        if ($user->status === User::STATUS_CANCELLED) {
            throw new ClBusinessException(BizCode::ACCOUNT_BANNED, '账号已注销，无法登录');
        }

        $user->update([
            'login_fail_count' => 0,
            'locked_until' => null,
            'last_login_ip' => $ip,
            'last_login_device' => $deviceId,
            'last_active_at' => Carbon::now(),
        ]);

        return $this->issueTokens($user);
    }

    /**
     * 刷新 token（旧 access_token 进黑名单）。
     *
     * @return array<string,int|string>
     */
    public function refresh(UnencryptedToken $token): array
    {
        $jwt = $this->jwt();
        $jwt->addBlackList($token);

        return [
            'access_token' => $jwt->builderAccessToken((string) $token->claims()->get(RegisteredClaims::ID))->toString(),
            'refresh_token' => $jwt->builderRefreshToken((string) $token->claims()->get(RegisteredClaims::ID))->toString(),
            'expire_at' => (int) $jwt->getConfig('ttl', 0),
        ];
    }

    public function logout(UnencryptedToken $token): void
    {
        $this->jwt()->addBlackList($token);
    }

    /**
     * 改密码（docs/05 第 4.1 节）：旧密码错 → 10006。
     */
    public function changePassword(User $user, string $oldPassword, string $newPassword): void
    {
        if (! $user->verifyPassword($oldPassword)) {
            throw new ClBusinessException(BizCode::OLD_PASSWORD_WRONG);
        }
        $user->update(['password' => password_hash($newPassword, \PASSWORD_BCRYPT)]);
    }

    /**
     * 注销账号（`docs/01` P15）。
     *
     * 二次确认由客户端做（要提示「余额/积分会清零」），这里只做服务端这一半：
     * 状态置成 `CANCELLED` + **把当前 token 拉黑**。
     *
     * 置成 CANCELLED 之后这个人就登不进来了（`findActiveUser` 返回 null → 401），
     * 别人也看不到他（关系、举报、发现那些地方都挡了 `STATUS_CANCELLED`）。
     *
     * **余额/积分不在这里动**：一来没有「注销清零」这种流水类型（钱一动就得有流水，铁律 6），
     * 二来 `docs/01` P15 写的是「提交后**后台处理**」—— 钱怎么清、什么时候清是后台的事（T7）。
     */
    public function cancel(User $user, UnencryptedToken $token): void
    {
        Db::transaction(function () use ($user, $token): void {
            $user->update(['status' => User::STATUS_CANCELLED]);
            $this->jwt()->addBlackList($token);
        });
    }

    /**
     * 取当前有效用户：不存在或已注销返回 null（中间件据此回 401）。
     */
    public function findActiveUser(int $userId): ?User
    {
        $user = $this->userRepository->findById($userId);
        if (! $user instanceof User || $user->status === User::STATUS_CANCELLED) {
            return null;
        }
        return $user;
    }

    /**
     * 封号检查：封禁中抛 10004（带 reason、until）；封禁已到期则自动解封。
     */
    public function assertNotBanned(User $user): void
    {
        if (! $user->isBanned()) {
            return;
        }
        if ($user->ban_until !== null && $user->ban_until->isPast()) {
            $user->update(['status' => User::STATUS_NORMAL, 'ban_until' => null, 'ban_reason' => null]);
            return;
        }

        $until = $user->ban_until?->format('Y-m-d H:i:s');
        $reason = (string) ($user->ban_reason ?? '');
        throw new ClBusinessException(
            BizCode::ACCOUNT_BANNED,
            sprintf('账号已被封禁：%s（解封时间 %s）', $reason, $until ?? '永久'),
            ['reason' => $user->ban_reason, 'until' => $until]
        );
    }

    /**
     * @return array<string,int|string>
     */
    private function issueTokens(User $user): array
    {
        $jwt = $this->jwt();
        return [
            'access_token' => $jwt->builderAccessToken((string) $user->id)->toString(),
            'refresh_token' => $jwt->builderRefreshToken((string) $user->id)->toString(),
            'expire_at' => (int) $jwt->getConfig('ttl', 0),
        ];
    }

    private function recordLoginFail(User $user): void
    {
        $count = $user->login_fail_count + 1;
        $data = ['login_fail_count' => $count];
        if ($count >= self::MAX_LOGIN_FAIL) {
            $data['locked_until'] = Carbon::now()->addMinutes(self::LOCK_MINUTES);
        }
        $user->update($data);
    }

    private function assertLoginRate(string $ip): void
    {
        $key = 'cl:login_rate:' . $ip;
        $count = (int) $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, 60);
        }
        if ($count > self::LOGIN_RATE_PER_MINUTE) {
            throw new ClBusinessException(BizCode::TOO_MANY_REQUESTS);
        }
    }
}
