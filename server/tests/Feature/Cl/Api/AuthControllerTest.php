<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointWallet;
use App\Model\Cl\User;
use App\Service\Cl\Auth\AppAuthService;
use Hyperf\Carbon\Carbon;
use Hyperf\Context\ApplicationContext;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use Mine\Jwt\Factory;
use PHPUnit\Framework\TestCase;

/**
 * App 认证接口（docs/05 第 4.1 节）。
 *
 * @internal
 * @coversNothing
 */
final class AuthControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private const PHONE = '13800138000';

    private const PASSWORD = 'abc12345';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->clearUsers();
        $this->clearLoginRate();
    }

    protected function tearDown(): void
    {
        $this->clearUsers();
        $this->clearLoginRate();
        parent::tearDown();
    }

    public function testRegisterSuccessCreatesUserAndCoinWallet(): void
    {
        $result = $this->register();

        self::assertSame(200, $result['code']);
        self::assertNotEmpty($result['data']['access_token']);
        self::assertNotEmpty($result['data']['refresh_token']);

        $user = User::query()->where('phone', self::PHONE)->first();
        self::assertInstanceOf(User::class, $user);
        self::assertSame(User::GENDER_MALE, $user->gender);
        self::assertSame(1, $user->source);
        // 密码必须加密存储
        self::assertNotSame(self::PASSWORD, $user->password);
        self::assertTrue(password_verify(self::PASSWORD, $user->password));
        // 男号注册自动建金币钱包（docs/04 第 4 节）
        self::assertNotNull(CoinWallet::query()->where('user_id', $user->id)->first());
        self::assertNull(PointWallet::query()->where('user_id', $user->id)->first());
    }

    public function testRegisterFemaleCreatesPointWallet(): void
    {
        $result = $this->register('13900139000', self::PASSWORD, User::GENDER_FEMALE, '女用户');

        self::assertSame(200, $result['code']);
        $user = User::query()->where('phone', '13900139000')->first();
        self::assertNotNull(PointWallet::query()->where('user_id', $user->id)->first());
        self::assertNull(CoinWallet::query()->where('user_id', $user->id)->first());
    }

    public function testRegisterDuplicatePhoneReturns10002(): void
    {
        $this->register();
        $result = $this->register();

        self::assertSame(10002, $result['code']);
    }

    public function testRegisterPasswordWithoutDigitReturns422(): void
    {
        $result = $this->register(self::PHONE, 'abcdefgh');

        self::assertSame(422, $result['code']);
    }

    public function testRegisterPasswordTooShortReturns422(): void
    {
        $result = $this->register(self::PHONE, 'a1');

        self::assertSame(422, $result['code']);
    }

    public function testRegisterInvalidPhoneReturns422(): void
    {
        $result = $this->register('12345');

        self::assertSame(422, $result['code']);
    }

    public function testLoginSuccess(): void
    {
        $this->register();
        $result = $this->login();

        self::assertSame(200, $result['code']);
        self::assertNotEmpty($result['data']['access_token']);
    }

    public function testLoginByAccountAlsoWorks(): void
    {
        $this->register();
        // 注册时 account = phone，用手机号登录即可
        self::assertSame(200, $this->login()['code']);
    }

    public function testLoginWrongPasswordReturns10001(): void
    {
        $this->register();
        $result = $this->login('wrong1234');

        self::assertSame(10001, $result['code']);
    }

    public function testLoginLockedAfterFiveFailures(): void
    {
        $this->register();

        for ($i = 1; $i <= AppAuthService::MAX_LOGIN_FAIL; ++$i) {
            self::assertSame(10001, $this->login('wrong1234')['code'], "第 {$i} 次错误密码应返回 10001");
        }

        // 第 6 次即使密码正确也要被锁
        self::assertSame(10003, $this->login(self::PASSWORD)['code']);
    }

    public function testLoginBannedUserReturns10004(): void
    {
        $this->register();
        $user = User::query()->where('phone', self::PHONE)->first();
        $user->update([
            'status' => User::STATUS_BANNED,
            'ban_reason' => '发布违规内容',
            'ban_until' => Carbon::now()->addDays(7),
        ]);

        $result = $this->login();

        self::assertSame(10004, $result['code']);
        self::assertSame('发布违规内容', $result['data']['reason']);
        self::assertNotEmpty($result['data']['until']);
    }

    public function testAdminTokenCannotAccessAppApi(): void
    {
        // 用后台场景（default）签发的 token 打 App 接口
        $adminToken = ApplicationContext::getContainer()
            ->get(Factory::class)
            ->get('default')
            ->builderAccessToken('1')
            ->toString();

        $result = $this->client->post('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer ' . $adminToken,
        ]);

        self::assertSame(401, $result['code']);
    }

    public function testAppTokenCannotAccessAdminApi(): void
    {
        $appToken = $this->register()['data']['access_token'];

        $result = $this->client->get('/admin/passport/getInfo', [], [
            'Authorization' => 'Bearer ' . $appToken,
        ]);

        self::assertSame(401, $result['code']);
    }

    public function testAppApiWithoutTokenReturns401(): void
    {
        self::assertSame(401, $this->client->post('/api/v1/auth/logout', [])['code']);
    }

    public function testChangePasswordAndLogout(): void
    {
        $token = $this->register()['data']['access_token'];
        $headers = ['Authorization' => 'Bearer ' . $token];

        // 旧密码错 → 10006
        self::assertSame(10006, $this->client->post('/api/v1/auth/password', [
            'old_password' => 'wrong1234',
            'new_password' => 'xyz98765',
        ], $headers)['code']);

        // 正常改密码
        self::assertSame(200, $this->client->post('/api/v1/auth/password', [
            'old_password' => self::PASSWORD,
            'new_password' => 'xyz98765',
        ], $headers)['code']);

        // 新密码能登录
        self::assertSame(200, $this->login('xyz98765')['code']);

        // 退出
        self::assertSame(200, $this->client->post('/api/v1/auth/logout', [], $headers)['code']);
        // 退出后 token 进黑名单
        self::assertSame(401, $this->client->post('/api/v1/auth/logout', [], $headers)['code']);
    }

    public function testRefreshToken(): void
    {
        $refresh = $this->register()['data']['refresh_token'];
        $result = $this->client->post('/api/v1/auth/refresh', ['refresh_token' => $refresh]);

        self::assertSame(200, $result['code']);
        self::assertNotEmpty($result['data']['access_token']);
    }

    public function testRefreshWithBadTokenReturns401(): void
    {
        $result = $this->client->post('/api/v1/auth/refresh', ['refresh_token' => 'not-a-token']);

        self::assertSame(401, $result['code']);
    }

    /**
     * @return array<string,mixed>
     */
    private function register(
        string $phone = self::PHONE,
        string $password = self::PASSWORD,
        int $gender = User::GENDER_MALE,
        string $nickname = '测试用户'
    ): array {
        return $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => $password,
            'confirm_password' => $password,
            'gender' => $gender,
            'nickname' => $nickname,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function login(string $password = self::PASSWORD): array
    {
        return $this->client->post('/api/v1/auth/login', [
            'account' => self::PHONE,
            'password' => $password,
            'device_id' => 'test-device',
        ]);
    }

    private function clearUsers(): void
    {
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }

    /**
     * 登录限流是 60 秒窗口，测试间要清掉，否则会被自己的限流挡住。
     */
    private function clearLoginRate(): void
    {
        $redis = make(Redis::class);
        $keys = $redis->keys('cl:login_rate:*');
        if (! empty($keys)) {
            $redis->del(...$keys);
        }
    }
}
