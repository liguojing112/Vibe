<?php

declare(strict_types=1);

namespace HyperfTests\Feature\Cl\Chat;

use App\Model\Cl\Conversation;
use App\Model\Cl\Message;
use App\Model\Cl\SensitiveWord;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\Testing\Concerns\RunTestsInCoroutine;
use PHPUnit\Framework\TestCase;

/**
 * 聊天三张表（docs/04 第 3 节）。
 *
 * 重点盯两条**约定**，它们后面被计费逻辑依赖：
 * 1. 会话**一对人只有一条**，按 `user_low_id < user_high_id` 存 —— 不管谁先发起都命中同一条；
 * 2. 消息的 `client_msg_id` 在**同一个发送者**下唯一（幂等，防重复扣费）。
 *
 * @internal
 * @coversNothing
 */
final class ChatTablesTest extends TestCase
{
    use RunTestsInCoroutine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearAll();
    }

    protected function tearDown(): void
    {
        $this->clearAll();
        parent::tearDown();
    }

    public function testPairIsOrderIndependent(): void
    {
        self::assertSame([3, 7], Conversation::pair(3, 7));
        self::assertSame([3, 7], Conversation::pair(7, 3));
        self::assertSame([5, 5], Conversation::pair(5, 5));
    }

    public function testOneConversationPerPair(): void
    {
        $this->makeConversation(3, 7, 7, 3);

        try {
            // 同一对人（同样归一化过的 ids）再建一次 → 唯一索引挡住
            $this->makeConversation(3, 7, 7, 3);
            self::fail('同一对人应该只能有一条会话');
        } catch (UniqueConstraintViolationException) {
            self::assertTrue(true);
        }

        self::assertSame(1, Conversation::query()->count());
    }

    public function testReversedPairIsRejectedByTheDatabase(): void
    {
        try {
            // 忘了归一化、直接反着写（low > high）→ CHECK 约束挡住。
            // 只靠唯一索引是挡不住的：(3,7) 和 (7,3) 在索引眼里是两行。
            $this->makeConversation(7, 3, 7, 3);
            self::fail('反着写应该被 CHECK 约束挡住');
        } catch (\Throwable $e) {
            self::assertStringContainsStringIgnoringCase('chk_cl_conversation_pair', $e->getMessage());
        }
    }

    public function testUnreadAndDeletedColumnsFollowTheUser(): void
    {
        $conversation = $this->makeConversation(3, 7, 7, 3, ['low_unread' => 2, 'high_unread' => 5]);

        self::assertSame('low_unread', $conversation->unreadColumnFor(3));
        self::assertSame('high_unread', $conversation->unreadColumnFor(7));
        self::assertSame(2, $conversation->unreadFor(3));
        self::assertSame(5, $conversation->unreadFor(7));

        // 删除会话是「各删各的」
        self::assertSame('low_deleted_at', $conversation->deletedAtColumnFor(3));
        self::assertSame('high_deleted_at', $conversation->deletedAtColumnFor(7));
    }

    public function testClientMsgIdIsUniquePerSender(): void
    {
        $conversation = $this->makeConversation(3, 7, 7, 3);
        $this->makeMessage($conversation->id, 3, 7, 'client-1');

        try {
            // 同一个人用同一个 client_msg_id 再发一次 → 唯一约束挡住（幂等靠它兜底）
            $this->makeMessage($conversation->id, 3, 7, 'client-1');
            self::fail('同一个发送者的 client_msg_id 应该唯一');
        } catch (UniqueConstraintViolationException) {
            self::assertTrue(true);
        }

        // 换个人可以用同样的 client_msg_id
        $this->makeMessage($conversation->id, 7, 3, 'client-1');
        self::assertSame(2, Message::query()->count());
    }

    public function testOnlyTextAndImageCountAsNormalMessages(): void
    {
        self::assertTrue((new Message(['type' => Message::TYPE_TEXT]))->isNormalMessage());
        self::assertTrue((new Message(['type' => Message::TYPE_IMAGE]))->isNormalMessage());
        // 礼物不计入 male_sent_count（06 第 10 节场景 16）
        self::assertFalse((new Message(['type' => Message::TYPE_GIFT]))->isNormalMessage());
        self::assertFalse((new Message(['type' => Message::TYPE_SYSTEM]))->isNormalMessage());
    }

    public function testMessageDefaults(): void
    {
        $conversation = $this->makeConversation(3, 7, 7, 3);
        $message = $this->makeMessage($conversation->id, 3, 7, 'client-2');

        self::assertSame(0, $message->price_coins);
        self::assertSame(0, $message->receiver_points);
        self::assertSame(0, $message->sensitive_hit);
        self::assertSame(Message::REVIEW_NONE, $message->review_status);
        self::assertNull($message->read_at);
        self::assertNull($message->free_reason);
    }

    public function testSensitiveWordIsUniqueAndEnabledByDefault(): void
    {
        $word = SensitiveWord::query()->create(['word' => '测试敏感词']);

        self::assertSame(1, $word->enabled);

        try {
            SensitiveWord::query()->create(['word' => '测试敏感词']);
            self::fail('敏感词应该唯一');
        } catch (UniqueConstraintViolationException) {
            self::assertTrue(true);
        }
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function makeConversation(
        int $lowId,
        int $highId,
        int $maleId,
        int $femaleId,
        array $attributes = [],
    ): Conversation {
        return Conversation::query()->create(array_merge([
            'user_low_id' => $lowId,
            'user_high_id' => $highId,
            'male_id' => $maleId,
            'female_id' => $femaleId,
        ], $attributes));
    }

    private function makeMessage(int $conversationId, int $senderId, int $receiverId, string $clientMsgId): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'client_msg_id' => $clientMsgId,
            'type' => Message::TYPE_TEXT,
            'content' => '你好',
        ]);
    }

    private function clearAll(): void
    {
        Message::query()->delete();
        Conversation::query()->delete();
        SensitiveWord::query()->delete();
    }
}
