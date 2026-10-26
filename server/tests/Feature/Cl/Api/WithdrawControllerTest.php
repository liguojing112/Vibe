<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\Notice;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Model\Cl\WithdrawAccount;
use App\Model\Cl\WithdrawOrder;
use App\Model\Enums\Cl\PointLogType;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 提现接口（docs/05 第 4.8 节）：`/withdraw/info`、`/withdraw/apply`、`/withdraw/records`。
 *
 * 规则本身在 `WithdrawServiceTest` 里测，这里只管接口接对了没有 + 业务码原样透出去
 * （页面要按 20002 弹「积分不足」、按 40001 提示原因）。
 *
 * @internal
 * @coversNothing
 */
final class WithdrawControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->clearAll();
        $this->resetConfig();
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testInfoReturnsTiersAndQuota(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, points: 12000);

        $result = $this->get($female, '/api/v1/withdraw/info');

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        $data = $result['data'];
        self::assertSame(120, $data['amount_yuan']);
        self::assertSame(12000, $data['points']);
        self::assertCount(4, $data['tiers']);
        self::assertSame([true, true, false, false], array_column($data['tiers'], 'enabled'));
        self::assertSame('提交后 1~3 个工作日内审核打款', $data['notice']);
        self::assertSame(1, $data['remaining_today']);
        self::assertNull($data['account'], '还没填过收款信息');
        self::assertTrue($data['real_name_passed']);
    }

    public function testApplyThenRecords(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, points: 5000);

        $apply = $this->post($female, '/api/v1/withdraw/apply', [
            'amount_yuan' => 50,
            'account_type' => 'ALIPAY',
            'account_no' => 'zhangsan@example.com',
        ]);

        self::assertSame(200, $apply['code'], json_encode($apply, JSON_UNESCAPED_UNICODE));
        self::assertSame(5000, $apply['data']['points']);
        self::assertSame(0, $apply['data']['balance']);
        self::assertSame(5000, $apply['data']['frozen']);
        self::assertNotEmpty($apply['data']['order_no']);

        $records = $this->get($female, '/api/v1/withdraw/records')['data'];
        self::assertSame(1, $records['total']);
        self::assertSame(50, intdiv($records['list'][0]['amount_fen'], 100));
        self::assertSame('待审核', $records['list'][0]['status_text']);
        self::assertSame('张三', $records['list'][0]['account_name']);

        // 收款信息被记下来了，下次默认带出来
        $info = $this->get($female, '/api/v1/withdraw/info')['data'];
        self::assertSame('zhangsan@example.com', $info['account']['account_no']);
        self::assertSame(0, $info['remaining_today']);
    }

    public function testNotEnoughPointsReturns20002(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, points: 4000);

        $result = $this->post($female, '/api/v1/withdraw/apply', [
            'amount_yuan' => 50,
            'account_type' => 'ALIPAY',
            'account_no' => 'a@b.com',
        ]);

        self::assertSame(20002, $result['code']);
        self::assertSame('积分不足', $result['message']);
        self::assertSame(['balance' => 4000, 'needed' => 5000], $result['data'], 'P28 要靠这两个数提示');
        self::assertSame(0, WithdrawOrder::query()->where('user_id', $female->id)->count());
    }

    public function testNotRealNameReturns40001(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, points: 5000, realNamePassed: false);

        $result = $this->post($female, '/api/v1/withdraw/apply', [
            'amount_yuan' => 50,
            'account_type' => 'WECHAT',
            'account_no' => 'wx-001',
        ]);

        self::assertSame(40001, $result['code']);
        self::assertSame('请先完成实名认证', $result['message']);
    }

    public function testMaleCannotOpenWithdrawPage(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);

        foreach (['/api/v1/withdraw/info', '/api/v1/withdraw/records'] as $url) {
            $result = $this->get($male, $url);
            self::assertSame(40001, $result['code'], $url);
            self::assertSame('提现只有女用户有', $result['message']);
        }
    }

    public function testApplyValidatesInput(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE, points: 100000);

        // 金额、收款方式必填；收款方式只认三种
        self::assertSame(422, $this->post($female, '/api/v1/withdraw/apply', ['account_type' => 'ALIPAY', 'account_no' => 'x'])['code']);
        self::assertSame(422, $this->post($female, '/api/v1/withdraw/apply', ['amount_yuan' => 50, 'account_no' => 'x'])['code']);
        self::assertSame(422, $this->post($female, '/api/v1/withdraw/apply', ['amount_yuan' => 50, 'account_type' => 'PAYPAL', 'account_no' => 'x'])['code']);
    }

    public function testNeedLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/withdraw/info')['code']);
        self::assertSame(401, $this->client->get('/api/v1/withdraw/records')['code']);
        // form 校验跑在鉴权之前，所以带合法参数才走得到 401
        self::assertSame(
            401,
            $this->client->post('/api/v1/withdraw/apply', [
                'amount_yuan' => 50,
                'account_type' => 'ALIPAY',
                'account_no' => 'a@b.com',
            ])['code'],
        );
    }

    // ---------- 工具 ----------

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(User $user, string $url, array $body): array
    {
        return $this->client->post($url, $body, ['Authorization' => 'Bearer ' . $this->tokens[$user->id]]);
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], ['Authorization' => 'Bearer ' . $this->tokens[$user->id]]);
    }

    private function registerUser(int $gender, int $points = 0, bool $realNamePassed = true): User
    {
        $phone = '13911111' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        $result = $this->client->post('/api/v1/auth/register', [
            'phone' => $phone,
            'password' => 'abc12345',
            'confirm_password' => 'abc12345',
            'gender' => $gender,
            'nickname' => '用户' . $this->sequence,
        ]);
        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));

        $user = User::query()->where('phone', $phone)->firstOrFail();
        $this->tokens[$user->id] = (string) $result['data']['access_token'];

        if ($points > 0) {
            make(PointWalletService::class)->increase($user->id, $points, PointLogType::MESSAGE_INCOME, 'test', $user->id, '测试收益');
        }
        if ($gender === User::GENDER_FEMALE && $realNamePassed) {
            User::query()->whereKey($user->id)->update(['real_name_status' => User::REAL_NAME_PASSED]);
            RealName::query()->create([
                'user_id' => $user->id,
                'real_name' => '张三',
                'id_card_encrypted' => 'x',
                'id_card_mask' => '110***********1233',
                'id_card_hash' => hash('sha256', '110101199003071233'),
                'status' => RealName::STATUS_PASSED,
            ]);
        }

        return $user->refresh();
    }

    private function resetConfig(): void
    {
        $config = make(AppConfigService::class);
        $config->set('point.per_yuan', 100);
        $config->set('withdraw.tiers', [50, 100, 200, 500]);
        $config->set('withdraw.daily_limit', 1);
        $config->set('withdraw.fee_rate', 0);
        $config->set('withdraw.need_real_name', 1);
        $config->set('withdraw.notice', '提交后 1~3 个工作日内审核打款');
    }

    private function clearAll(): void
    {
        Notice::query()->delete();
        WithdrawAccount::query()->delete();
        WithdrawOrder::query()->delete();
        RealName::query()->delete();
        PointLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
