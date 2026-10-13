<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Chat;

use App\Model\Cl\Block;
use App\Model\Cl\CoinWallet;
use App\Model\Cl\Conversation;
use App\Model\Cl\Follow;
use App\Model\Cl\Message;
use App\Model\Cl\PointWallet;
use App\Model\Cl\ProfileAudit;
use App\Model\Cl\Report;
use App\Model\Cl\SensitiveWord;
use App\Model\Cl\User;
use App\Model\Cl\UserPhoto;
use App\Service\Cl\Chat\MessageRetentionService;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Carbon\Carbon;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 私信留存与清理（T4.9 / docs/04 第 3 节、docs/06 第 2.1 节）。
 *
 * 四条验收全在这里：**超期普通消息被删、敏感词消息保留、被举报消息保留、last_message 回退正确**。
 *
 * @internal
 * @coversNothing
 */
final class MessageRetentionTest extends TestCase
{
    use RunTestsInCoroutine;

    private MessageRetentionService $retention;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
        make(AppConfigService::class)->set('chat.message_retention_months', 6);

        $this->retention = make(MessageRetentionService::class);
    }

    protected function tearDown(): void
    {
        make(AppConfigService::class)->set('chat.message_retention_months', 6);
        $this->clearAll();
        parent::tearDown();
    }

    // ---------- 基本规则 ----------

    public function testExpiredNormalMessagesAreDeletedAndFreshOnesKept(): void
    {
        $old = $this->message(monthsAgo: 7);
        $fresh = $this->message(monthsAgo: 1);

        $result = $this->retention->cleanup();

        self::assertSame(1, $result['deleted']);
        self::assertSame(6, $result['months']);
        self::assertNull(Message::query()->find($old->id), '超过 6 个月的消息该被物理删掉');
        self::assertNotNull(Message::query()->find($fresh->id), '1 个月前的不该动');
    }

    /** 敏感词命中的是监管/取证证据，超期也不能删（docs/04 第 3 节）。 */
    public function testSensitiveMessagesAreKept(): void
    {
        $hit = $this->message(monthsAgo: 7, sensitiveHit: 1);
        $normal = $this->message(monthsAgo: 7);

        $this->retention->cleanup();

        self::assertNotNull(Message::query()->find($hit->id), '敏感词命中的要长期保留');
        self::assertNull(Message::query()->find($normal->id));
    }

    /** 被举报关联的消息长期保留（`cl_report.message_id`）。 */
    public function testReportedMessagesAreKept(): void
    {
        $reported = $this->message(monthsAgo: 7);
        $other = $this->message(monthsAgo: 7);
        $this->report($reported);

        $this->retention->cleanup();

        self::assertNotNull(Message::query()->find($reported->id), '被举报的消息要留着取证');
        self::assertNull(Message::query()->find($other->id));
    }

    /** 只清「普通消息」：礼物牵着钱、系统消息牵着通知，都留着。 */
    public function testGiftAndSystemMessagesAreKept(): void
    {
        $text = $this->message(monthsAgo: 7, type: Message::TYPE_TEXT);
        $gift = $this->message(monthsAgo: 7, type: Message::TYPE_GIFT);
        $system = $this->message(monthsAgo: 7, type: Message::TYPE_SYSTEM);

        $this->retention->cleanup();

        self::assertNull(Message::query()->find($text->id));
        self::assertNotNull(Message::query()->find($gift->id), '礼物消息关联着平台收入');
        self::assertNotNull(Message::query()->find($system->id));
    }

    // ---------- 会话的最后一条消息 ----------

    /** 验收项：`last_message_id` 指着被删的那条时，要回退到剩下最新的一条。 */
    /** 验收项：`last_message_id` 指着被删的那条时，要回退到剩下最新的一条。 */
    public function testLastMessageIdFallsBackToTheNewestSurvivor(): void
    {
        $conversation = $this->conversation();
        $oldest = $this->message(monthsAgo: 9, conversationId: $conversation->id);
        $survivor = $this->message(monthsAgo: 1, conversationId: $conversation->id);
        $middle = $this->message(monthsAgo: 8, conversationId: $conversation->id);
        // 故意让「最后一条」指向那条会被删的（比如中途出过岔子、老数据没维护对）
        $conversation->update(['last_message_id' => $middle->id]);

        $result = $this->retention->cleanup();

        self::assertSame(2, $result['deleted']);
        self::assertSame(1, $result['conversations']);
        self::assertSame(
            $survivor->id,
            (int) $conversation->refresh()->last_message_id,
            '指向的那条没了就要回退到剩下的最新一条（不是最早那条）',
        );
        self::assertNull(Message::query()->find($oldest->id));
    }

    public function testLastMessageIdBecomesNullWhenNothingIsLeft(): void
    {
        $conversation = $this->conversation();
        $only = $this->message(monthsAgo: 8, conversationId: $conversation->id);
        $conversation->update(['last_message_id' => $only->id]);

        $this->retention->cleanup();

        self::assertNull($conversation->refresh()->last_message_id, '一条都不剩就置空，不能留着已删的 id');
    }

    public function testUntouchedConversationsKeepTheirLastMessage(): void
    {
        $conversation = $this->conversation();
        $fresh = $this->message(monthsAgo: 1, conversationId: $conversation->id);
        $conversation->update(['last_message_id' => $fresh->id]);

        $this->retention->cleanup();

        self::assertSame($fresh->id, (int) $conversation->refresh()->last_message_id);
    }

    // ---------- 配置 ----------

    /** 保留月数从配置读，后台改了就生效（docs/06 第 2.1 节）。 */
    public function testRetentionMonthsComeFromConfig(): void
    {
        $twoMonthsAgo = $this->message(monthsAgo: 2);

        make(AppConfigService::class)->set('chat.message_retention_months', 1);
        $result = $this->retention->cleanup();

        self::assertSame(1, $result['months']);
        self::assertNull(Message::query()->find($twoMonthsAgo->id), '改成保留 1 个月后，2 个月前的就该清掉');
    }

    /** 后台把保留月数填 0 = 永久保留，什么都不删。 */
    public function testZeroMonthsMeansKeepForever(): void
    {
        $ancient = $this->message(monthsAgo: 120);
        make(AppConfigService::class)->set('chat.message_retention_months', 0);

        $result = $this->retention->cleanup();

        self::assertSame(0, $result['deleted']);
        self::assertNull($result['cutoff']);
        self::assertNotNull(Message::query()->find($ancient->id));
    }

    /** 命令/定时任务可以临时覆盖月数（`--months=`）。 */
    public function testExplicitMonthsOverrideConfig(): void
    {
        $threeMonthsAgo = $this->message(monthsAgo: 3);

        $result = $this->retention->cleanup(2);

        self::assertSame(2, $result['months']);
        self::assertNull(Message::query()->find($threeMonthsAgo->id));
    }

    // ---------- 工具 ----------

    private function message(
        int $monthsAgo,
        int $type = Message::TYPE_TEXT,
        int $sensitiveHit = 0,
        ?int $conversationId = null,
    ): Message {
        $at = Carbon::now()->subMonths($monthsAgo);

        return Message::query()->create([
            'conversation_id' => $conversationId ?? $this->conversation()->id,
            'sender_id' => 101,
            'receiver_id' => 102,
            'client_msg_id' => 'c-' . (++$this->sequence),
            'type' => $type,
            'content' => '第 ' . $this->sequence . ' 条',
            'sensitive_hit' => $sensitiveHit,
            'created_at' => $at->toDateTimeString(),
            'updated_at' => $at->toDateTimeString(),
        ]);
    }

    private function report(Message $message): Report
    {
        return Report::query()->create([
            'reporter_id' => 102,
            'target_id' => 101,
            'reason' => Report::REASON_ABUSE,
            'content' => '这条我要举报',
            'message_id' => $message->id,
        ]);
    }

    private function conversation(): Conversation
    {
        $index = ++$this->sequence;

        return Conversation::query()->create([
            'user_low_id' => 100 + $index,
            'user_high_id' => 900 + $index,
            'male_id' => 100 + $index,
            'female_id' => 900 + $index,
        ]);
    }

    private function clearAll(): void
    {
        Report::query()->delete();
        Message::query()->delete();
        Conversation::query()->delete();
        SensitiveWord::query()->delete();
        Follow::query()->delete();
        Block::query()->delete();
        UserPhoto::query()->delete();
        ProfileAudit::query()->delete();
        PointWallet::query()->delete();
        CoinWallet::query()->delete();
        User::query()->delete();
    }
}
