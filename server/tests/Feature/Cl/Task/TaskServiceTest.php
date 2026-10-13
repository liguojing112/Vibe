<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Task;

use App\Exception\ClBusinessException;
use App\Model\Cl\CoinLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Message;
use App\Model\Cl\PointLog;
use App\Model\Cl\PointWallet;
use App\Model\Cl\SignIn;
use App\Model\Cl\Task;
use App\Model\Cl\TaskLog;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Model\Enums\Cl\BizCode;
use App\Model\Enums\Cl\CoinLogType;
use App\Model\Enums\Cl\PointLogType;
use App\Service\Cl\Task\TaskService;
use App\Service\Cl\Wallet\CoinWalletService;
use App\Service\Cl\Wallet\PointWalletService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 每日任务（docs/06 第 7 节）。
 *
 * 四条硬规则在这里断言死：
 * - **同一周期只能领一次**（唯一索引 `(user_id, task_id, period_key)`）；
 * - 任务按性别过滤，**男发金币、女发积分**；
 * - 签到一次点击就拿到奖励，重复点不重复发；
 * - **任务奖励积分不计入累计收益**（场景 20，`total_earned_points` 只统计私信和礼物分成）。
 *
 * @internal
 * @coversNothing
 */
final class TaskServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private TaskService $tasks;

    /** @var array<string,int> 任务类型 => 男任务 id */
    private array $maleTaskIds = [];

    /** @var array<string,int> 任务类型 => 女任务 id */
    private array $femaleTaskIds = [];

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        $this->seedTasks();

        $this->tasks = make(TaskService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 列表与性别过滤 ----------

    public function testListOnlyShowsTasksForMyGender(): void
    {
        $male = $this->makeMale();
        $female = $this->makeFemale();

        $maleList = $this->tasks->list($male)['list'];
        $femaleList = $this->tasks->list($female)['list'];

        self::assertCount(4, $maleList);
        self::assertCount(4, $femaleList);
        // 两条列表的 id 完全不重叠：男女是不同的任务行
        self::assertSame([], array_intersect(array_column($maleList, 'id'), array_column($femaleList, 'id')));
        self::assertSame('每日签到', $maleList[0]['name']);
        self::assertSame('+5 金币', $maleList[0]['reward_text']);
        self::assertSame('+20 积分', $femaleList[0]['reward_text']);
    }

    public function testListHidesDisabledTasks(): void
    {
        $male = $this->makeMale();
        Task::query()->whereKey($this->maleTaskIds[Task::TYPE_PROFILE_BIO])->update(['enabled' => 0]);

        $list = $this->tasks->list($male)['list'];

        self::assertCount(3, $list);
        self::assertNotContains('写一句自我介绍', array_column($list, 'name'));
    }

    public function testUnfinishedTaskIsTodoWithGotoButton(): void
    {
        $male = $this->makeMale();

        $photo = $this->findInList($this->tasks->list($male)['list'], $this->maleTaskIds[Task::TYPE_PROFILE_PHOTO]);

        self::assertSame('todo', $photo['status']);
        self::assertSame('去完成', $photo['button_text']);
        self::assertSame('edit_profile', $photo['action']);
        self::assertSame(0, $photo['progress']);
        self::assertSame(3, $photo['target']);
    }

    public function testUnfinishedTaskCannotBeClaimed(): void
    {
        $male = $this->makeMale();

        try {
            $this->tasks->claim($male, $this->maleTaskIds[Task::TYPE_PROFILE_BIO]);
            self::fail('没完成不该能领');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }

        self::assertSame(0, CoinLog::query()->where('user_id', $male->id)->where('type', CoinLogType::TASK->value)->count());
    }

    public function testTaskOfTheOtherGenderCannotBeClaimed(): void
    {
        $male = $this->makeMale();

        try {
            $this->tasks->claim($male, $this->femaleTaskIds[Task::TYPE_SIGN_IN]);
            self::fail('不该能领异性的任务');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }
    }

    // ---------- 完成条件 ----------

    public function testPhotoTaskNeedsEnoughApprovedPhotos(): void
    {
        $male = $this->makeMale();
        $taskId = $this->maleTaskIds[Task::TYPE_PROFILE_PHOTO];

        // 3 张里只有 2 张过审 → 还不算完成，进度是 2
        $this->addPhoto($male, UserPhoto::AUDIT_APPROVED);
        $this->addPhoto($male, UserPhoto::AUDIT_APPROVED);
        $this->addPhoto($male, UserPhoto::AUDIT_PENDING);
        $todo = $this->findInList($this->tasks->list($male)['list'], $taskId);
        self::assertSame('todo', $todo['status']);
        self::assertSame(2, $todo['progress']);

        // 第 3 张过审 → 可领取
        $this->addPhoto($male, UserPhoto::AUDIT_APPROVED);
        $claimable = $this->findInList($this->tasks->list($male)['list'], $taskId);
        self::assertSame('claimable', $claimable['status']);
        self::assertSame('领取', $claimable['button_text']);
        self::assertSame('claim', $claimable['action']);
        self::assertSame(3, $claimable['progress']);

        $result = $this->tasks->claim($male, $taskId);
        self::assertSame(20, $result['reward']);
        self::assertSame(20, $result['balance'], '男的发金币');
    }

    public function testBioTaskIsDoneWhenBioIsNotBlank(): void
    {
        $male = $this->makeMale(bio: '喜欢爬山和看电影');

        $item = $this->findInList($this->tasks->list($male)['list'], $this->maleTaskIds[Task::TYPE_PROFILE_BIO]);

        self::assertSame('claimable', $item['status']);
        self::assertSame(1, $item['progress']);
    }

    public function testSendMessageTaskCountsDistinctPartnersToday(): void
    {
        $male = $this->makeMale();
        $taskId = $this->maleTaskIds[Task::TYPE_SEND_MESSAGE];

        // 给同一个人发 3 条 = 1 个不同的人，不算完成
        $this->addMessage($male, receiverId: 201);
        $this->addMessage($male, receiverId: 201);
        $this->addMessage($male, receiverId: 201);
        self::assertSame(1, $this->findInList($this->tasks->list($male)['list'], $taskId)['progress']);

        // 再给两个人各发一条 → 3 个不同的人，完成
        $this->addMessage($male, receiverId: 202);
        $this->addMessage($male, receiverId: 203);
        self::assertSame('claimable', $this->findInList($this->tasks->list($male)['list'], $taskId)['status']);

        // 礼物消息不算「打招呼」（礼物和私信一直是两套口径）
        $male2 = $this->makeMale();
        $this->addMessage($male2, receiverId: 301);
        $this->addMessage($male2, receiverId: 302);
        $this->addMessage($male2, receiverId: 303, type: Message::TYPE_GIFT);
        self::assertSame(
            2,
            $this->findInList($this->tasks->list($male2)['list'], $this->maleTaskIds[Task::TYPE_SEND_MESSAGE])['progress'],
        );
    }

    public function testYesterdayMessagesDoNotCount(): void
    {
        $male = $this->makeMale();
        Carbon::setTestNow(Carbon::now()->subDay());
        $this->addMessage($male, receiverId: 401);
        $this->addMessage($male, receiverId: 402);
        $this->addMessage($male, receiverId: 403);
        Carbon::setTestNow();

        $item = $this->findInList($this->tasks->list($male)['list'], $this->maleTaskIds[Task::TYPE_SEND_MESSAGE]);

        self::assertSame(0, $item['progress'], '每日任务只看今天');
    }

    // ---------- 签到 ----------

    public function testSignInGrantsRewardInOneTap(): void
    {
        $male = $this->makeMale();

        $result = $this->tasks->signIn($male);

        self::assertTrue($result['signed']);
        self::assertSame(5, $result['reward']);
        self::assertSame(5, $result['balance'], '男的发金币');
        self::assertSame('+5 金币', $result['reward_text']);
        self::assertSame(1, SignIn::query()->where('user_id', $male->id)->count());
        self::assertSame(
            CoinLogType::TASK->value,
            CoinLog::query()->where('user_id', $male->id)->firstOrFail()->type,
        );
        self::assertSame('claimed', $this->findInList($this->tasks->list($male)['list'], $this->maleTaskIds[Task::TYPE_SIGN_IN])['status']);
    }

    public function testSignInTwiceInADayGrantsOnlyOnce(): void
    {
        $male = $this->makeMale();

        $this->tasks->signIn($male);
        $second = $this->tasks->signIn($male);

        self::assertTrue($second['signed'], '重复签到不该报错');
        self::assertSame(5, $second['balance'], '余额没再涨');
        self::assertSame(1, TaskLog::query()->where('user_id', $male->id)->count());
        self::assertSame(1, CoinLog::query()->where('user_id', $male->id)->where('type', CoinLogType::TASK->value)->count());
    }

    public function testSignInRewardResetsNextDay(): void
    {
        $male = $this->makeMale();
        $this->tasks->signIn($male);
        self::assertSame(5, make(CoinWalletService::class)->balanceOf($male->id));

        // 第二天：换了 period_key 也换了 sign_date，可以再签一次
        Carbon::setTestNow(Carbon::now()->addDay());
        $this->tasks->signIn($male);

        self::assertSame(10, make(CoinWalletService::class)->balanceOf($male->id));
        self::assertSame(2, SignIn::query()->where('user_id', $male->id)->count());
        self::assertSame(2, TaskLog::query()->where('user_id', $male->id)->count());
    }

    public function testSignInTaskCannotBeClaimedThroughClaim(): void
    {
        $male = $this->makeMale();
        $this->tasks->signIn($male);

        try {
            $this->tasks->claim($male, $this->maleTaskIds[Task::TYPE_SIGN_IN]);
            self::fail('签到任务的奖励只能靠签到拿');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }
    }

    // ---------- 同一周期只能领一次 ----------

    public function testClaimTwiceIsRejected(): void
    {
        $male = $this->makeMale(bio: '自我介绍');
        $taskId = $this->maleTaskIds[Task::TYPE_PROFILE_BIO];

        $first = $this->tasks->claim($male, $taskId);
        self::assertSame(10, $first['reward']);

        try {
            $this->tasks->claim($male, $taskId);
            self::fail('同一周期不该能领第二次');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }

        self::assertSame(10, make(CoinWalletService::class)->balanceOf($male->id), '第二次没再发钱');
        self::assertSame(1, TaskLog::query()->where('user_id', $male->id)->count());
    }

    public function testOnceTaskNeverResetsButDailyTaskDoes(): void
    {
        $male = $this->makeMale(bio: '自我介绍');
        $this->tasks->claim($male, $this->maleTaskIds[Task::TYPE_PROFILE_BIO]);

        $this->addMessage($male, 501);
        $this->addMessage($male, 502);
        $this->addMessage($male, 503);
        $this->tasks->claim($male, $this->maleTaskIds[Task::TYPE_SEND_MESSAGE]);

        Carbon::setTestNow(Carbon::now()->addDay());
        $list = $this->tasks->list($male)['list'];

        // 一次性任务：第二天照样是「已完成」
        self::assertSame('claimed', $this->findInList($list, $this->maleTaskIds[Task::TYPE_PROFILE_BIO])['status']);
        // 每日任务：新的一天重新开始（今天还没发过消息）
        $sendMessage = $this->findInList($list, $this->maleTaskIds[Task::TYPE_SEND_MESSAGE]);
        self::assertSame('todo', $sendMessage['status']);
        self::assertSame(0, $sendMessage['progress']);
    }

    // ---------- 场景 20：任务奖励积分不计入累计收益 ----------

    public function testScene20TaskRewardDoesNotCountIntoTotalEarnedPoints(): void
    {
        $female = $this->makeFemale();
        $before = (int) $female->refresh()->total_earned_points;

        $result = $this->tasks->signIn($female);

        self::assertSame(20, $result['reward']);
        self::assertSame(20, $result['balance'], '女用户发积分');
        self::assertSame('+20 积分', $result['reward_text']);

        $female->refresh();
        self::assertSame($before, (int) $female->total_earned_points, '任务奖励不算累计收益（升级只认私信和礼物分成）');
        self::assertSame(
            PointLogType::TASK->value,
            PointLog::query()->where('user_id', $female->id)->firstOrFail()->type,
        );
    }

    public function testFemalePointRewardGoesToPointWalletNotCoinWallet(): void
    {
        $female = $this->makeFemale();
        $this->addPhoto($female, UserPhoto::AUDIT_APPROVED, 3);

        $result = $this->tasks->claim($female, $this->femaleTaskIds[Task::TYPE_PROFILE_PHOTO]);

        self::assertSame(50, $result['reward']);
        self::assertSame(50, $result['balance']);
        self::assertSame(50, (int) make(PointWalletService::class)->summaryOf($female->id)['balance']);
        self::assertSame(0, make(CoinWalletService::class)->balanceOf($female->id), '女用户不拿金币');
    }

    // ---------- 工具 ----------

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
     * 和 `databases/seeders/cl_task_seeder_20261013.php` 同一份配置（男女各一行）。
     * 这里抄一遍而不是读 seeder 类：测试要能脱离种子文件单独跑
     * （和 `GiftBillingTest` 抄那 6 个礼物是一个道理）。
     */
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

    private function addPhoto(User $user, int $status, int $count = 1): void
    {
        for ($i = 0; $i < $count; ++$i) {
            UserPhoto::query()->create([
                'user_id' => $user->id,
                'url' => 'https://cdn.example.com/p' . (++$this->sequence) . '.jpg',
                'sort' => $this->sequence,
                'audit_status' => $status,
            ]);
        }
    }

    private function addMessage(User $sender, int $receiverId, int $type = Message::TYPE_TEXT): void
    {
        Message::query()->create([
            'conversation_id' => 1,
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'client_msg_id' => 't' . (++$this->sequence),
            'type' => $type,
            'content' => '你好',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function makeMale(int $coins = 0, string $bio = ''): User
    {
        $male = $this->makeUser(User::GENDER_MALE, $bio);
        make(CoinWalletService::class)->createForUser($male->id);
        if ($coins > 0) {
            make(CoinWalletService::class)->increase($male->id, $coins, CoinLogType::RECHARGE, 'test', $male->id, '测试充值');
        }

        return $male->refresh();
    }

    private function makeFemale(string $bio = ''): User
    {
        $female = $this->makeUser(User::GENDER_FEMALE, $bio);
        make(PointWalletService::class)->createForUser($female->id);

        return $female->refresh();
    }

    private function makeUser(int $gender, string $bio): User
    {
        $index = ++$this->sequence;

        return User::query()->create([
            'account' => 'task' . $index,
            'phone' => null,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => '用户' . $index,
            'bio' => $bio,
            'level' => 1,
            'real_name_status' => User::REAL_NAME_PASSED,
            'status' => User::STATUS_NORMAL,
        ]);
    }

    private function clearAll(): void
    {
        SignIn::query()->delete();
        TaskLog::query()->delete();
        Task::query()->delete();
        CoinLog::query()->delete();
        PointLog::query()->delete();
        Message::query()->delete();
        UserPhoto::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
