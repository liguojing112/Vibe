<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Api;

use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\SignIn;
use App\Model\Cl\Task;
use App\Model\Cl\TaskLog;
use App\Model\Cl\User;
use App\Model\Enums\Cl\CoinLogType;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Testing\Client;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 每日任务接口（docs/05 第 4.9 节）：`/task/list`、`/task/sign-in`、`/task/claim`。
 *
 * 规则本身在 `TaskServiceTest` 里测，这里只管「接口接对了没有、鉴权挡住没有」。
 *
 * @internal
 * @coversNothing
 */
final class TaskControllerTest extends TestCase
{
    use RunTestsInCoroutine;

    private Client $client;

    private int $sequence = 0;

    /** @var array<int,string> userId => token */
    private array $tokens = [];

    /** @var array<string,int> 任务类型 => 男任务 id */
    private array $maleTaskIds = [];

    /** @var array<string,int> 任务类型 => 女任务 id */
    private array $femaleTaskIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = make(Client::class);
        $this->tokens = [];
        $this->maleTaskIds = [];
        $this->femaleTaskIds = [];
        $this->clearAll();
        $this->seedTasks();
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testListReturnsMyGenderTasks(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);
        $female = $this->registerUser(User::GENDER_FEMALE);

        $maleList = $this->get($male, '/api/v1/task/list')['data']['list'];
        $femaleList = $this->get($female, '/api/v1/task/list')['data']['list'];

        self::assertCount(4, $maleList);
        self::assertCount(4, $femaleList);
        self::assertSame('每日签到', $maleList[0]['name']);
        self::assertSame('📅', $maleList[0]['icon']);
        self::assertSame('连续签到可领取玫瑰', $maleList[0]['description']);
        self::assertSame('签到', $maleList[0]['button_text']);
        self::assertSame('sign_in', $maleList[0]['action']);
        self::assertSame('+5 金币', $maleList[0]['reward_text']);
        self::assertSame('+20 积分', $femaleList[0]['reward_text']);
    }

    public function testSignInGrantsRewardAndShowsCompletedAfterwards(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);

        $result = $this->post($male, '/api/v1/task/sign-in');

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertTrue($result['data']['signed']);
        self::assertSame(5, $result['data']['reward']);
        self::assertSame(5, $result['data']['balance']);
        self::assertSame(5, make(CoinWalletService::class)->balanceOf($male->id));

        $list = $this->get($male, '/api/v1/task/list')['data']['list'];
        $signIn = $this->findInList($list, $this->maleTaskIds[Task::TYPE_SIGN_IN]);
        self::assertSame('claimed', $signIn['status']);
        self::assertSame('已完成', $signIn['button_text']);
        self::assertSame('none', $signIn['action']);
        self::assertSame(1, $signIn['progress']);
    }

    public function testSignInTwiceDoesNotPayTwice(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);

        $this->post($male, '/api/v1/task/sign-in');
        $second = $this->post($male, '/api/v1/task/sign-in');

        self::assertSame(200, $second['code']);
        self::assertSame(5, $second['data']['balance']);
        self::assertSame(1, SignIn::query()->where('user_id', $male->id)->count());
    }

    public function testClaimRewardsCompletedTask(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);
        $male->fill(['bio' => '喜欢爬山'])->save();

        $result = $this->post($male, '/api/v1/task/claim', ['task_id' => $this->maleTaskIds[Task::TYPE_PROFILE_BIO]]);

        self::assertSame(200, $result['code'], json_encode($result, JSON_UNESCAPED_UNICODE));
        self::assertSame(10, $result['data']['reward']);
        self::assertSame(10, $result['data']['balance']);
        self::assertSame(CoinLogType::TASK->value, CoinLog::query()->where('user_id', $male->id)->firstOrFail()->type);
    }

    public function testClaimUnfinishedTaskReturns40001(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);

        $result = $this->post($male, '/api/v1/task/claim', ['task_id' => $this->maleTaskIds[Task::TYPE_PROFILE_BIO]]);

        self::assertSame(40001, $result['code']);
        self::assertSame('任务还没完成', $result['message']);
        self::assertSame(0, TaskLog::query()->where('user_id', $male->id)->count());
    }

    public function testClaimTwiceReturns40001(): void
    {
        $female = $this->registerUser(User::GENDER_FEMALE);
        $female->fill(['bio' => '自我介绍'])->save();
        $taskId = $this->femaleTaskIds[Task::TYPE_PROFILE_BIO];

        $first = $this->post($female, '/api/v1/task/claim', ['task_id' => $taskId]);
        self::assertSame(200, $first['code']);
        self::assertSame(30, $first['data']['balance'], '女用户发积分');

        $second = $this->post($female, '/api/v1/task/claim', ['task_id' => $taskId]);
        self::assertSame(40001, $second['code']);
        self::assertSame(30, make(PointWalletService::class)->summaryOf($female->id)['balance']);
    }

    public function testClaimValidatesTaskId(): void
    {
        $male = $this->registerUser(User::GENDER_MALE);

        $result = $this->post($male, '/api/v1/task/claim');

        self::assertSame(422, $result['code']);
    }

    public function testNeedLogin(): void
    {
        // form 校验跑在鉴权之前，所以「未登录」要带合法参数才走得到 401 那一步
        self::assertSame(401, $this->client->get('/api/v1/task/list')['code']);
        self::assertSame(401, $this->client->post('/api/v1/task/sign-in')['code']);
        self::assertSame(401, $this->client->post('/api/v1/task/claim', ['task_id' => 1])['code']);
    }

    /**
     * @param array<int,array<string,mixed>> $list
     * @return array<string,mixed>
     */
    private function findInList(array $list, int $taskId): array
    {
        foreach ($list as $item) {
            if ($item['id'] === $taskId) {
                return $item;
            }
        }

        self::fail('列表里没有任务 ' . $taskId);
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    private function post(User $user, string $url, array $body = []): array
    {
        return $this->client->post($url, $body, $this->headers($user));
    }

    /**
     * @return array<string,mixed>
     */
    private function get(User $user, string $url): array
    {
        return $this->client->get($url, [], $this->headers($user));
    }

    /**
     * @return array<string,string>
     */
    private function headers(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->tokens[$user->id]];
    }

    private function registerUser(int $gender): User
    {
        $phone = '13600000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
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

    private function seedTasks(): void
    {
        $rows = [
            [Task::TYPE_SIGN_IN, '每日签到', '连续签到可领取玫瑰', '📅', 1, Task::PERIOD_DAILY, 1, 5, 20],
            [Task::TYPE_PROFILE_PHOTO, '完善照片', '上传 3 张生活照，展示更多面的你', '📷', 3, Task::PERIOD_ONCE, 2, 20, 50],
            [Task::TYPE_PROFILE_BIO, '写一句自我介绍', '让别人更快了解你', '✍️', 1, Task::PERIOD_ONCE, 3, 10, 30],
            [Task::TYPE_SEND_MESSAGE, '和新朋友打招呼', '今天向 3 位新朋友发出问候', '💬', 3, Task::PERIOD_DAILY, 4, 5, 20],
        ];

        foreach ($rows as [$type, $name, $desc, $icon, $target, $period, $sort, $maleReward, $femaleReward]) {
            foreach ([Task::GENDER_MALE => $maleReward, Task::GENDER_FEMALE => $femaleReward] as $gender => $reward) {
                $id = (int) Task::query()->create([
                    'type' => $type,
                    'name' => $name,
                    'description' => $desc,
                    'icon' => $icon,
                    'target' => $target,
                    'period' => $period,
                    'sort' => $sort,
                    'gender' => $gender,
                    'reward' => $reward,
                    'enabled' => 1,
                ])->id;

                if ($gender === Task::GENDER_MALE) {
                    $this->maleTaskIds[$type] = $id;
                } else {
                    $this->femaleTaskIds[$type] = $id;
                }
            }
        }
    }

    private function clearAll(): void
    {
        SignIn::query()->delete();
        TaskLog::query()->delete();
        Task::query()->delete();
        CoinLog::query()->delete();
        PointLog::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
