<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinWallet;
use App\Model\Cl\LevelConfig;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\RealName;
use App\Model\Cl\User;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 实名接口（docs/05 第 4.8 节）：`/realname`、`/realname/submit`。
 *
 * 规则本身在 `RealNameServiceTest` 里测，这里只管「接口接对了没有」——
 * 特别是**返回给 App 的永远是打码的号**（`docs/01` P29）。
 *
 * @internal
 * @coversNothing
 */
final class RealNameControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private const VALID_ID = '110101199003071233';

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
        $this->seedLevels();
        make(AppConfigService::class)->set('share.unverified_factor', 5000);
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testStatusBeforeSubmit(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);

        $result = $this->get($female, '/api/v1/realname');

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertSame(0, $result['data']['status']);
        self::assertSame('未认证', $result['data']['status_text']);
        self::assertTrue($result['data']['can_submit']);
    }

    public function testSubmitReturnsMaskedIdCardOnly(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);

        $result = $this->post($female, '/api/v1/realname/submit', [
            'real_name' => '张三',
            'id_card' => self::VALID_ID,
        ]);

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        $data = $result['data'];
        self::assertSame(1, $data['status']);
        self::assertSame('审核中', $data['status_text']);
        self::assertSame('110***********1233', $data['id_card_mask']);
        self::assertFalse($data['can_submit']);
        // 响应里不能出现完整号码
        self::assertStringNotContainsString(self::VALID_ID, json_encode($result, JSON_UNESCAPED_UNICODE));

        // 再查一次状态也是打码的
        $again = $this->get($female, '/api/v1/realname');
        self::assertSame('110***********1233', $again['data']['id_card_mask']);
    }

    public function testBadChecksumReturns40003(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);

        $result = $this->post($female, '/api/v1/realname/submit', [
            'real_name' => '张三',
            'id_card' => '110101199003071234',
        ]);

        self::assertSame(40003, $result['code']);
        self::assertSame('实名信息格式错误', $result['message']);
        self::assertSame(0, RealName::query()->where('user_id', $female->id)->count());
    }

    public function testSecondSubmitWhileAuditingReturns40002(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);
        $body = ['real_name' => '张三', 'id_card' => self::VALID_ID];
        $this->post($female, '/api/v1/realname/submit', $body);

        $result = $this->post($female, '/api/v1/realname/submit', $body);

        self::assertSame(40002, $result['code']);
        self::assertSame('实名认证审核中，请勿重复提交', $result['message']);
    }

    public function testMissingFieldReturns422(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);

        $result = $this->post($female, '/api/v1/realname/submit', ['real_name' => '张三']);

        self::assertSame(422, $result['code']);
    }

    public function testNeedLogin(): void
    {
        self::assertSame(401, $this->client->get('/api/v1/realname')['code']);
        // form 校验跑在鉴权之前，所以带合法参数才走得到 401
        self::assertSame(
            401,
            $this->client->post('/api/v1/realname/submit', ['real_name' => '张三', 'id_card' => self::VALID_ID])['code'],
        );
    }

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

    private function registerUser(int $gender): User
    {
        $phone = '13811111' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
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

        return $user;
    }

    private function seedLevels(): void
    {
        $rows = [
            1 => [0, 5, 3000],
            2 => [20000, 8, 3500],
            3 => [100000, 10, 4000],
        ];

        foreach ($rows as $level => [$threshold, $price, $rate]) {
            LevelConfig::query()->create([
                'level' => $level,
                'name' => 'Lv' . $level,
                'threshold_points' => $threshold,
                'message_price' => $price,
                'message_share_rate' => $rate,
                'voice_price' => 60 * $level,
                'video_price' => 120 * $level,
            ]);
        }
    }

    private function clearAll(): void
    {
        Notice::query()->delete();
        RealName::query()->delete();
        LevelConfig::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
