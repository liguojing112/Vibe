<?php

declare(strict_types=1);

namespace App\Service\Cl\Ai;

use App\Model\Cl\AiPersona;
use App\Model\Cl\AiReplyLog;
use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Repository\Cl\AiPersonaRepository;
use App\Repository\Cl\AiReplyLogRepository;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Chat\MessageBillingService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * AI 代聊：女用户账号背后由模型自动回复 / 主动搭话。
 *
 * **只由服务端驱动**，客户端完全不知情：AI 生成的回复走的是正常的
 * `MessageBillingService::send()`（女 → 男），所以计费、推送、敏感词、会话列表全都和真人一致。
 *
 * 三条安全线：
 * 1. 只在**活跃时段**内、未超过**每日上限**时回；
 * 2. 男用户身上不可能有人设，所以 AI 发出的消息不会再触发一次 AI（天然防循环）；
 * 3. 身份追问先岔开，**连续追问就承认**是平台安排的 AI 助手 —— 再绕下去就是欺骗了。
 */
final class AiChatService
{
    /** 连续被问几次就承认（第一次打太极，第二次承认）。 */
    private const IDENTITY_ADMIT_AFTER = 2;

    public function __construct(
        private readonly AiPersonaRepository $personaRepository,
        private readonly AiReplyLogRepository $logRepository,
        private readonly DeepSeekClient $client,
        private readonly MessageRepository $messageRepository,
        private readonly MessageBillingService $billingService,
        private readonly UserRepository $userRepository,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly Redis $redis,
    ) {}

    /**
     * 收到一条消息后调用（**异步**，不阻塞发消息的请求）。
     *
     * 传 id 而不是对象：触发点在 Controller 里，那里只拿得到返回数组。
     */
    public function handleIncomingById(int $messageId): void
    {
        if ($messageId <= 0) {
            return;
        }

        Coroutine::create(function () use ($messageId): void {
            try {
                $message = $this->messageRepository->findById($messageId);
                if ($message instanceof Message) {
                    $this->replyTo($message);
                }
            } catch (Throwable $e) {
                $this->logger->warning('AI 代聊回复失败：' . $e->getMessage());
            }
        });
    }

    /**
     * 定时任务调用：让某个女用户主动找个人搭话。
     *
     * @return bool 有没有真的发出去
     */
    public function proactive(AiPersona $persona): bool
    {
        if ($persona->proactive <= AiPersona::PROACTIVE_OFF) {
            return false;
        }

        $female = $this->userRepository->findById($persona->user_id);
        if (! $female instanceof User || ! $female->isFemale()) {
            return false;
        }
        if (! $this->canReplyNow($persona)) {
            return false;
        }

        $target = $this->pickProactiveTarget($female->id, $persona);
        if (! $target instanceof User) {
            return false;
        }

        $messages = $this->buildProactiveMessages($persona, $female, $target);
        $result = $this->client->chat($messages);
        if ($result === null || $result['content'] === '') {
            return false;
        }

        return $this->deliver($persona, $female, $target, $result, AiReplyLog::KIND_PROACTIVE);
    }

    // ---------- 自动回复 ----------

    private function replyTo(Message $incoming): void
    {
        // 只处理「男 → 女」：女用户收到消息才有代聊
        $female = $this->userRepository->findById($incoming->receiver_id);
        if (! $female instanceof User || ! $female->isFemale()) {
            return;
        }

        $persona = $this->personaRepository->findByUser($female->id);
        if (! $persona instanceof AiPersona || $persona->enabled !== 1) {
            return;
        }
        if (! $this->canReplyNow($persona)) {
            return;
        }

        // 同一会话同时只允许一个 AI 在生成：连发几条消息只回一次，
        // 也避免两个协程并发往同一对话插消息（会撞唯一键，表现为「消息发送失败」）
        $lockKey = 'ai:reply:' . $incoming->conversation_id;
        if (! $this->redis->set($lockKey, '1', ['NX', 'EX' => 60])) {
            return;
        }

        try {
            $male = $this->userRepository->findById($incoming->sender_id);
            if (! $male instanceof User || ! $male->isMale()) {
                return;
            }

            $messages = $this->buildReplyMessages($persona, $female, $incoming);
            $result = $this->client->chat($messages);
            if ($result === null || $result['content'] === '') {
                return;
            }

            $this->deliver($persona, $female, $male, $result, AiReplyLog::KIND_REPLY, (int) $incoming->conversation_id);
        } finally {
            $this->redis->del($lockKey);
        }
    }

    // ---------- 公共 ----------

    /**
     * 现在能不能回：开关、状态、活跃时段。
     *
     * **不限制每日条数**（客户要求）：`replied_today` 只用来在后台看今天回了多少，
     * 不再作为拦截条件。
     */
    private function canReplyNow(AiPersona $persona): bool
    {
        if ($persona->enabled !== 1 || $persona->status !== AiPersona::STATUS_NORMAL) {
            return false;
        }

        return $this->inActiveWindow($persona);
    }

    /** 活跃时段，支持跨零点（22 点到 2 点）。两个值相同表示全天。 */
    private function inActiveWindow(AiPersona $persona): bool
    {
        $hour = (int) date('G');
        $start = $persona->active_start;
        $end = $persona->active_end;

        if ($start === $end) {
            return true;
        }

        return $start < $end
            ? ($hour >= $start && $hour < $end)
            : ($hour >= $start || $hour < $end);
    }

    /**
     * 发出去 + 记账。
     *
     * @param array{content: string, prompt_tokens: int, completion_tokens: int} $result
     */
    private function deliver(
        AiPersona $persona,
        User $female,
        User $target,
        array $result,
        string $kind,
        ?int $conversationId = null,
    ): bool {
        // 模拟真人打字：按字数算 2~7 秒，别秒回
        $delay = min(7, max(2, (int) ceil(mb_strlen($result['content']) / 10)));
        sleep($delay);

        // 并发下偶尔会撞消息表的唯一键（表现为「消息发送失败」），换一个 client_msg_id 再试一次
        $sent = null;
        for ($try = 1; $try <= 2; ++$try) {
            $clientMsgId = sprintf('ai%d_%s', $persona->id, bin2hex(random_bytes(8)));

            try {
                $sent = $this->billingService->send($female, $target->id, Message::TYPE_TEXT, $result['content'], $clientMsgId);
                break;
            } catch (Throwable $e) {
                $this->logger->warning(sprintf('AI 代聊发送失败（第 %d 次）：%s', $try, $e->getMessage()));
            }
        }

        if ($sent === null) {
            return false;
        }

        $this->personaRepository->recordUsage(
            $persona->id,
            $result['prompt_tokens'],
            $result['completion_tokens'],
            date('Y-m-d'),
        );

        $this->logRepository->createLog([
            'persona_id' => $persona->id,
            'user_id' => $female->id,
            'to_user_id' => $target->id,
            'conversation_id' => $conversationId ?? ($sent['message']['conversation_id'] ?? null),
            'message_id' => $sent['message']['id'] ?? null,
            'kind' => $kind,
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
        ]);

        return true;
    }

    // ---------- 提示词 ----------

    /**
     * @return array<int,array{role:string,content:string}>
     */
    private function buildReplyMessages(AiPersona $persona, User $female, Message $incoming): array
    {
        $history = $this->messageRepository->historyOf((int) $incoming->conversation_id, null, $this->contextSize());
        $askedTimes = $this->countIdentityQuestions($history->all(), $female->id, $incoming);
        $admit = $askedTimes >= self::IDENTITY_ADMIT_AFTER;

        // historyOf 是 orderByDesc(id)（最新的在前），喂给模型之前必须转回时间正序 ——
        // 否则模型看到「最新的话在最前面」，会以为对方刚开口，于是每条都在回「在呢」并重编一遍当下的状态。
        $rows = array_reverse($history->all());

        // 兜底：刚收到的那条如果还没进 history（极端时序），补到最后
        $last = $rows === [] ? null : $rows[count($rows) - 1];
        if ($last === null || $last->id !== $incoming->id) {
            $rows[] = $incoming;
        }

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($persona, $female, $admit)]];

        foreach ($rows as $row) {
            if ($row->type !== Message::TYPE_TEXT || trim((string) $row->content) === '') {
                continue;
            }
            $messages[] = [
                'role' => $row->sender_id === $female->id ? 'assistant' : 'user',
                'content' => (string) $row->content,
            ];
        }

        return $messages;
    }

    /**
     * @return array<int,array{role:string,content:string}>
     */
    private function buildProactiveMessages(AiPersona $persona, User $female, User $target): array
    {
        $system = $this->systemPrompt($persona, $female, false)
            . "\n- 现在是你主动找对方聊天：根据对方资料自然地开个头，一句话，不要显得刻意，不要群发感";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => sprintf(
                '对方资料：%s，%s岁，城市%s，职业%s。请主动发第一句话。',
                $target->nickname,
                (string) $target->age(),
                (string) $target->city,
                (string) $target->job,
            )],
        ];
    }

    private function systemPrompt(AiPersona $persona, User $female, bool $admitIdentity): string
    {
        $lines = [];
        $lines[] = sprintf(
            '你在扮演一个真实的女用户：昵称%s，%s岁，城市%s，职业%s。',
            $female->nickname,
            (string) $female->age(),
            (string) $female->city,
            (string) $female->job,
        );
        if ($persona->persona !== '') {
            $lines[] = '人设：' . $persona->persona;
        }
        if ($persona->tone !== '') {
            $lines[] = '说话风格：' . $persona->tone;
        }
        if ($persona->taboos !== '') {
            $lines[] = '这些话题不聊，被提到就轻轻带过：' . $persona->taboos;
        }

        $lines[] = '要求：';
        $lines[] = '- 像用微信聊天，一次只说一两句，别长篇大论，标点随意';
        $lines[] = '- 对方问什么就先回答什么，不要答非所问';
        $lines[] = '- 不要每条都以「在呢」「在的」开头，也不要反复描述自己此刻在干嘛';
        $lines[] = '- 不要说「作为一个AI」「我是人工智能」这类话，也不要用客服腔';
        $lines[] = '- 不要每句都反问，也不要过分热情';
        $lines[] = $admitIdentity
            ? '- 对方又在追问你是不是机器人：这次如实说你是平台安排的 AI 助手，语气自然一点，别道歉太多'
            : '- 如果对方问你是不是AI/机器人：不要承认也不要否认，自然岔开，比如「你怎么会这么想」';

        return implode("\n", $lines);
    }

    /**
     * 这条会话里对方问过几次「你是不是 AI / 机器人」。
     *
     * @param array<int,Message> $history
     */
    private function countIdentityQuestions(array $history, int $femaleId, Message $incoming): int
    {
        $pattern = '/你是.{0,6}(ai|机器人|真人|人工智能|程序)|(ai|机器人|真人).{0,2}吗|是不是(ai|机器人|真人)/iu';
        $count = 0;

        foreach ($history as $row) {
            if ($row->sender_id === $femaleId) {
                continue;
            }
            if (preg_match($pattern, (string) $row->content) === 1) {
                ++$count;
            }
        }

        // 刚收到的这条还没进 history（取决于调用时机），单独判一次
        if (preg_match($pattern, (string) $incoming->content) === 1) {
            ++$count;
        }

        return $count;
    }

    private function contextSize(): int
    {
        return max(4, (int) $this->config->get('ai.context_messages', 12));
    }

    /**
     * 给主动搭话挑一个对象：最近活跃的男用户。
     *
     * 两层去重：这个人设 24 小时内找过的人不重复打扰；**任何女号 1 小时内已经找过的人也不再找**
     * （否则几个号会在同一轮里一起给同一个在线用户发消息）。
     */
    private function pickProactiveTarget(int $femaleId, AiPersona $persona): ?User
    {
        $recent = $this->logRepository->recentProactiveTargets(
            $persona->id,
            date('Y-m-d H:i:s', time() - 86400),
        );
        $busy = $this->logRepository->busyTargets(date('Y-m-d H:i:s', time() - 3600));
        $exclude = array_values(array_unique(array_merge($recent, $busy)));

        return $this->userRepository->getQuery()
            ->where('gender', User::GENDER_MALE)
            ->where('status', User::STATUS_NORMAL)
            ->where('id', '!=', $femaleId)
            // 半小时内活跃过的，说明在线
            ->where('last_active_at', '>=', date('Y-m-d H:i:s', time() - 1800))
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('id', $exclude))
            ->inRandomOrder()
            ->first();
    }
}
