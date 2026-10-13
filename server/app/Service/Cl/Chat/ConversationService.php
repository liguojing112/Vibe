<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat;

use App\Exception\ClBusinessException;
use App\Model\Cl\Conversation;
use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Model\Enums\Cl\BizCode;
use App\Repository\Cl\ConversationRepository;
use Hyperf\Database\Exception\UniqueConstraintViolationException;

/**
 * 会话的公共动作（发私信和送礼物都要用）。
 *
 * 抽出来是因为两处逻辑必须**完全一致**：会话只有一条、谁先建谁后建都要落到同一条上、
 * 新消息要让双方「删过的」会话重新出现。
 */
final class ConversationService
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
    ) {}

    /**
     * 拿到会话并**加行锁**；还没有就建一条（并发首次发消息时靠唯一索引兜底）。
     *
     * 上锁是为了把「这对男女的免费条数」串起来：并发发两条、只剩 1 条免费时，
     * 第二个请求必须等第一个提交后再判断，否则两条都会免单。
     */
    public function lockOrCreate(User $sender, User $receiver): Conversation
    {
        $conversation = $this->conversationRepository->lockByPair($sender->id, $receiver->id);
        if ($conversation !== null) {
            return $conversation;
        }

        [$low, $high] = Conversation::pair($sender->id, $receiver->id);
        $maleId = $sender->isMale() ? $sender->id : $receiver->id;
        $femaleId = $sender->isMale() ? $receiver->id : $sender->id;

        try {
            return $this->conversationRepository->createPair($low, $high, $maleId, $femaleId);
        } catch (UniqueConstraintViolationException) {
            // 另一个请求刚好建好了，锁住它继续
            $conversation = $this->conversationRepository->lockByPair($sender->id, $receiver->id);
            if ($conversation !== null) {
                return $conversation;
            }

            throw new ClBusinessException(BizCode::STATUS_NOT_ALLOWED, '会话创建失败，请重试');
        }
    }

    /**
     * 落一条新消息后的会话维护：对方未读 +1、更新最后一条、清掉双方的「删除」标记。
     *
     * `male_sent_count` **只算男方发的普通消息**（礼物不算，docs/06 第 10 节场景 16）——
     * 它决定「前 N 条免费」的额度，礼物本来就不走私信计费，把它算进去会让用户白少一次免费。
     */
    public function touch(Conversation $conversation, Message $message, User $sender, User $receiver): void
    {
        $unreadColumn = $conversation->unreadColumnFor($receiver->id);
        $conversation->{$unreadColumn} = (int) $conversation->{$unreadColumn} + 1;

        if ($sender->isMale() && $message->isNormalMessage()) {
            $conversation->male_sent_count = (int) $conversation->male_sent_count + 1;
        }

        $conversation->last_message_id = (int) $message->id;
        $conversation->last_message_at = $message->created_at;
        // 新消息让双方「删过的」会话重新出现
        $conversation->low_deleted_at = null;
        $conversation->high_deleted_at = null;
        $conversation->save();
    }
}
