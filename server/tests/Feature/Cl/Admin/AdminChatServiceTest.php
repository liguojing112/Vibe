<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Admin;

use App\Exception\ClBusinessException;
use App\Model\Cl\AdminActionLog;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Message;
use App\Model\Cl\Notice;
use App\Model\Cl\PointWallet;
use App\Model\Cl\SensitiveWord;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Model\Permission\User as AdminUser;
use App\Service\Cl\Admin\AdminChatService;
use App\Service\Cl\Admin\AdminSensitiveWordService;
use App\Service\Cl\Chat\SensitiveWordService;
use Hyperf\Redis\Redis;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

use function Hyperf\Support\make;

/**
 * 后台聊天管理：会话记录、待审消息、敏感词库（docs/07 第 3.6~3.8 节）。
 *
 * @internal
 * @coversNothing
 */
final class AdminChatServiceTest extends TestCase
{
    use RunTestsInCoroutine;

    private AdminChatService $service;

    private AdminSensitiveWordService $wordService;

    private AdminUser $operator;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clear();
        $this->service = make(AdminChatService::class);
        $this->wordService = make(AdminSensitiveWordService::class);
        // 上一次跑失败中断时 tearDown 不会执行，先清干净
        AdminUser::query()->where('username', 'shenhe')->delete();
        $this->operator = AdminUser::create(['username' => 'shenhe', 'password' => '123456', 'nickname' => '审核员']);
    }

    protected function tearDown(): void
    {
        $this->clear();
        AdminUser::query()->where('username', 'shenhe')->delete();
        parent::tearDown();
    }

    // ---------- 会话记录 ----------

    public function testConversationListCarriesBothSidesAndSentCount(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $this->makeMessage($conversation, $male, $female, '你好呀', price: 10, points: 4);

        $result = $this->service->conversations([], 1, 20);

        self::assertSame(1, $result['total']);
        $row = $result['list'][0];
        self::assertSame('阿宁', $row['male_nickname']);
        self::assertSame('晚晴', $row['female_nickname']);
        self::assertSame(2, (int) $row['male_sent_count'], '男方已发条数来自会话表');
    }

    public function testConversationListFiltersByEitherSide(): void
    {
        [$male, $female] = $this->makePair();
        $this->makeAppUser(User::GENDER_MALE, '林川');

        self::assertSame(1, $this->service->conversations(['user_id' => $male->id], 1, 20)['total']);
        self::assertSame(1, $this->service->conversations(['user_id' => $female->id], 1, 20)['total']);
        self::assertSame(0, $this->service->conversations(['user_id' => 999999], 1, 20)['total']);
    }

    public function testConversationMessagesCarryPriceAndPoints(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $this->makeMessage($conversation, $male, $female, '收费的', price: 10, points: 4);
        $this->makeMessage($conversation, $male, $female, '免费的', freeReason: 'PAIR_FREE');

        $result = $this->service->conversationMessages((int) $conversation->id, 1, 20);

        self::assertSame(2, $result['total']);
        // 按 id 倒序：最新那条在前
        self::assertSame('免费的', $result['list'][0]['content']);
        self::assertSame('PAIR_FREE', $result['list'][0]['free_reason']);
        self::assertSame(10, (int) $result['list'][1]['price_coins']);
        self::assertSame(4, (int) $result['list'][1]['receiver_points']);
    }

    // ---------- 待审消息 ----------

    public function testPendingListOnlyReturnsHitMessagesAndMarksWords(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $this->wordService->create($this->operator, '加微信');
        $hit = $this->makeMessage($conversation, $male, $female, '你加微信吧', reviewStatus: Message::REVIEW_PENDING);
        $this->makeMessage($conversation, $male, $female, '普通消息');

        $result = $this->service->pendingMessages(1, 20);

        self::assertSame(1, $result['total']);
        self::assertSame($hit->id, (int) $result['list'][0]['id']);
        self::assertSame(['加微信'], $result['list'][0]['hit_words'], '命中的词要标出来给前端标红');
    }

    public function testMarkNormalAndViolation(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $first = $this->makeMessage($conversation, $male, $female, 'a', reviewStatus: Message::REVIEW_PENDING);
        $second = $this->makeMessage($conversation, $male, $female, 'b', reviewStatus: Message::REVIEW_PENDING);

        $this->service->markMessages($this->operator, [(int) $first->id], Message::REVIEW_NORMAL);
        self::assertSame(Message::REVIEW_NORMAL, (int) $first->refresh()->review_status);

        $this->service->markMessages($this->operator, [(int) $second->id], Message::REVIEW_VIOLATION);
        self::assertSame(Message::REVIEW_VIOLATION, (int) $second->refresh()->review_status);
    }

    /** 标记违规时可以顺手封了发送方 —— 走的是和用户管理同一个封禁入口。 */
    public function testViolationWithBanReasonBansTheSender(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $message = $this->makeMessage($conversation, $male, $female, '违规内容', reviewStatus: Message::REVIEW_PENDING);

        $result = $this->service->markMessages($this->operator, [(int) $message->id], Message::REVIEW_VIOLATION, 7, '发布违规内容');

        self::assertTrue($result['banned']);
        $male->refresh();
        self::assertSame(User::STATUS_BANNED, $male->status);
        self::assertSame('发布违规内容', $male->ban_reason);
        self::assertSame(1, AdminActionLog::query()->where('user_id', $male->id)->where('action', AdminActionLog::ACTION_BAN)->count());
        self::assertSame(1, Notice::query()->where('user_id', $male->id)->where('biz_type', 'BAN')->count());
    }

    public function testViolationWithoutReasonOnlyMarks(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $message = $this->makeMessage($conversation, $male, $female, '违规内容', reviewStatus: Message::REVIEW_PENDING);

        $result = $this->service->markMessages($this->operator, [(int) $message->id], Message::REVIEW_VIOLATION);

        self::assertFalse($result['banned']);
        self::assertSame(User::STATUS_NORMAL, $male->refresh()->status, '没填原因就不封号');
    }

    // ---------- 敏感词库 ----------

    public function testCreateRejectsDuplicate(): void
    {
        $this->wordService->create($this->operator, '赌博');

        $this->expectException(ClBusinessException::class);
        $this->wordService->create($this->operator, '赌博');
    }

    /** 改完词库要**立刻生效**（服务端有缓存，不清的话新词不生效）。 */
    public function testChangingWordsFlushesCacheSoHitTakesEffectImmediately(): void
    {
        $sensitive = make(SensitiveWordService::class);
        self::assertFalse($sensitive->hit('我们来赌博吧'), '词库里还没有这个词');

        $created = SensitiveWord::query()->create(['word' => '赌博', 'enabled' => 1, 'creator_id' => $this->operator->id]);
        $this->wordService->create($this->operator, '诈骗');

        self::assertTrue($sensitive->hit('我们来赌博吧'), '新词要立刻生效');
        self::assertSame(['赌博'], $sensitive->matches('我们来赌博吧'));

        // 停用之后就不该再命中
        $this->wordService->update($this->operator, (int) $created->id, '赌博', false);
        self::assertFalse($sensitive->hit('我们来赌博吧'));
    }

    public function testImportSkipsDuplicatesAndBlankLines(): void
    {
        $this->wordService->create($this->operator, '赌博');

        $result = $this->wordService->import($this->operator, "赌博\n诈骗\n\n  代开发票  \n");

        self::assertSame(2, $result['added']);
        self::assertSame(['赌博'], $result['skipped'], '重复的要跳过并报出来');
        self::assertSame(3, SensitiveWord::query()->count());
    }

    public function testDeleteRemovesWords(): void
    {
        $this->wordService->create($this->operator, '赌博');
        $this->wordService->create($this->operator, '诈骗');

        $deleted = $this->wordService->remove($this->operator, SensitiveWord::query()->pluck('id')->map('intval')->all());

        self::assertSame(2, $deleted);
        self::assertSame(0, SensitiveWord::query()->count());
    }

    public function testRejectsEmptyMessageSelection(): void
    {
        $this->expectException(ClBusinessException::class);
        $this->service->markMessages($this->operator, [], Message::REVIEW_NORMAL);
    }

    /** 只能标正常/违规，乱传别的状态不行。 */
    public function testRejectsUnknownReviewStatus(): void
    {
        [$male, $female, $conversation] = $this->makePair();
        $message = $this->makeMessage($conversation, $male, $female, 'a');

        try {
            $this->service->markMessages($this->operator, [(int) $message->id], Message::REVIEW_PENDING);
            self::fail('待审状态不该能手动标');
        } catch (ClBusinessException $e) {
            self::assertSame(BizCode::STATUS_NOT_ALLOWED->value, $e->getResponse()->code);
        }
    }

    // ---------- 工具 ----------

    /** @return array{0: User, 1: User, 2: Conversation} */
    private function makePair(): array
    {
        $male = $this->makeAppUser(User::GENDER_MALE, '阿宁');
        $female = $this->makeAppUser(User::GENDER_FEMALE, '晚晴');

        /** @var Conversation $conversation */
        $conversation = Conversation::query()->create([
            'user_low_id' => min($male->id, $female->id),
            'user_high_id' => max($male->id, $female->id),
            'male_id' => $male->id,
            'female_id' => $female->id,
            'male_sent_count' => 2,
            'last_message_at' => date('Y-m-d H:i:s'),
        ]);

        return [$male, $female, $conversation];
    }

    private function makeMessage(
        Conversation $conversation,
        User $sender,
        User $receiver,
        string $content,
        int $price = 0,
        int $points = 0,
        ?string $freeReason = null,
        int $reviewStatus = Message::REVIEW_NONE,
    ): Message {
        /** @var Message $message */
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'client_msg_id' => 'c' . (++$this->sequence),
            'type' => Message::TYPE_TEXT,
            'content' => $content,
            'price_coins' => $price,
            'free_reason' => $freeReason,
            'receiver_points' => $points,
            'sensitive_hit' => $reviewStatus === Message::REVIEW_PENDING ? 1 : 0,
            'review_status' => $reviewStatus,
        ]);

        return $message;
    }

    private function makeAppUser(int $gender, string $nickname): User
    {
        $phone = '13500000' . str_pad((string) (++$this->sequence), 3, '0', STR_PAD_LEFT);
        /** @var User $user */
        $user = User::query()->create([
            'account' => $phone,
            'phone' => $phone,
            'password' => password_hash('abc12345', PASSWORD_BCRYPT),
            'gender' => $gender,
            'nickname' => $nickname,
            'avatar' => 'https://cdn.example.com/a.jpg',
            'status' => User::STATUS_NORMAL,
            'source' => 1,
        ]);
        CoinWallet::query()->create(['user_id' => $user->id]);
        PointWallet::query()->create(['user_id' => $user->id]);

        return $user;
    }

    private function clear(): void
    {
        make(Redis::class)->del('cl:sensitive_words');
        SensitiveWord::query()->delete();
        AdminActionLog::query()->delete();
        Notice::query()->delete();
        Message::query()->delete();
        Conversation::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
