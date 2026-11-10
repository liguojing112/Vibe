<?php

declare(strict_types=1);

namespace App\Service\Cl\Ai;

use App\Model\Cl\AiPersona;
use App\Model\Cl\BotChatLog;
use App\Model\Cl\BotChatPair;
use App\Model\Cl\BotChatRule;
use App\Model\Cl\BotChatTarget;
use App\Model\Cl\Message;
use App\Model\Cl\User;
use App\Repository\Cl\AiPersonaRepository;
use App\Repository\Cl\BlockRepository;
use App\Repository\Cl\BotChatLogRepository;
use App\Repository\Cl\BotChatPairRepository;
use App\Repository\Cl\BotChatRuleRepository;
use App\Repository\Cl\BotChatTargetRepository;
use App\Repository\Cl\ConversationRepository;
use App\Repository\Cl\MessageRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Chat\ConversationService;
use App\Service\Cl\Chat\SensitiveWordService;
use App\Service\Cl\Config\AppConfigService;
use App\Service\Cl\Pricing\MessagePricingService;
use App\Service\Cl\Push\PushService;
use Hyperf\Coroutine\Coroutine;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * AI 男女互聊：机器人男号给指定女号发消息。
 *
 * **和 `MessageBillingService` 是两条完全独立的通道**：
 * - 不扣金币、不给积分、不记平台收入、不写任何钱包流水；
 * - 消息落库 `price_coins = 0`、`receiver_points = 0`、`free_reason = BOT_MALE`；
 * - 女号的「收益」只在本模块的 `cl_bot_chat_target.virtual_points_today` 里计数，
 *   用来判断当天是否已达上限，**不是钱**。
 *
 * 不走 `MessageBillingService` 加参数：那条链路是全站最敏感的部分，
 * 一个 `freeMode` 参数就等于给「免费发付费私信」开了后门。
 *
 * 女号如果开了 AI 代聊，回复由 `AiChatService` 负责（走女→男的正常免费通道）。
 */
final class BotChatService
{
    /** 机器人消息的免费原因标记（女 AI 回复时靠它反查来源）。 */
    public const FREE_REASON_BOT_MALE = 'BOT_MALE';

    public function __construct(
        private readonly BotChatRuleRepository $ruleRepository,
        private readonly BotChatTargetRepository $targetRepository,
        private readonly BotChatPairRepository $pairRepository,
        private readonly BotChatLogRepository $logRepository,
        private readonly AiPersonaRepository $personaRepository,
        private readonly MessageRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly BlockRepository $blockRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationService $conversationService,
        private readonly SensitiveWordService $sensitiveWordService,
        private readonly MessagePricingService $pricingService,
        private readonly PushService $pushService,
        private readonly AiChatService $aiChatService,
        private readonly DeepSeekClient $client,
        private readonly AppConfigService $configService,
        private readonly LoggerInterface $logger,
    ) {}

    /** 全局开关（后台「AI 互聊总开关」）。 */
    public function enabled(): bool
    {
        return (bool) $this->configService->get('bot_chat.enabled', false);
    }

    /**
     * 给一个目标女号推进一次互聊。
     *
     * @param array<int,User> $males 已解析好的男号池（手动 + 号池），由调用方查一次传进来
     * @return bool 这一轮有没有真的发出消息
     */
    public function tick(BotChatRule $rule, BotChatTarget $target, array $males, string $today): bool
    {
        if (! $this->enabled() || $rule->enabled !== BotChatRule::STATUS_RUNNING) {
            return false;
        }
        // 规则设成「只在后台手动发」时，定时任务不再碰它
        if ($rule->auto_send !== BotChatRule::AUTO_SEND_ON) {
            return false;
        }
        if (! $rule->inActiveWindow()) {
            return false;
        }

        // 跨天先重置，再重新读一次
        $this->targetRepository->resetDailyIfStale((int) $target->id, $today);
        $target = $this->targetRepository->findById($target->id);
        if (! $target instanceof BotChatTarget || $target->isDailyLimitReached()) {
            return false;
        }

        $female = $this->userRepository->findById($target->female_id);
        if (! $female instanceof User || ! $female->isFemale() || $female->status !== User::STATUS_NORMAL) {
            return false;
        }

        $male = $this->pickMale($rule, $female, $males, $today);
        if (! $male instanceof User) {
            return false;
        }

        // 拉黑跳过：被拉黑了还发消息，只会让女号在消息列表里看到一个已经屏蔽的人
        if ($this->blockRepository->blockedEitherWay($female->id, $male->id)) {
            return false;
        }

        $pair = $this->pairRepository->ensure((int) $rule->id, $male->id, $female->id, $today);
        if ((int) $pair->rounds_today >= $rule->round_limit_per_pair) {
            return false;
        }
        if (! $this->intervalPassed($pair, $rule)) {
            return false;
        }

        return $this->sendFromMale($rule, $target, $female, $male, $pair);
    }

    /**
     * 后台点「发送」：立刻让这条规则的男号给目标女号发一条。
     *
     * 和定时任务的区别只有一处 —— **不检查活跃时段、不检查自动发送开关**，
     * 因为这就是运营在按按钮，他说现在发就是现在发。
     * 但今天还能不能发仍然受规则约束：轮数、间隔、每日上限一条都不放宽，
     * 否则这个按钮就成了绕开限流的后门。
     *
     * @return array{sent: bool, message: null|string, reason: null|string}
     */
    public function sendNow(BotChatRule $rule, string $today): array
    {
        if (! $this->enabled()) {
            return $this->fail('AI 互聊总开关未打开');
        }
        if ($rule->enabled !== BotChatRule::STATUS_RUNNING) {
            return $this->fail('规则已暂停，请先启动');
        }

        $targets = $this->targetRepository->listActiveByRule((int) $rule->id);
        if ($targets === []) {
            return $this->fail('这条规则还没有目标女号');
        }

        $males = $this->resolveMales($rule);
        if ($males === []) {
            return $this->fail('这条规则没有可用的男号（检查男号状态或号池）');
        }

        $sent = 0;
        $skipped = [];

        foreach ($targets as $target) {
            $this->targetRepository->resetDailyIfStale((int) $target->id, $today);
            $target = $this->targetRepository->findById($target->id);

            if (! $target instanceof BotChatTarget) {
                continue;
            }
            if ($target->isDailyLimitReached()) {
                $skipped[] = '已达当日上限';

                continue;
            }

            $female = $this->userRepository->findById($target->female_id);
            if (! $female instanceof User || ! $female->isFemale() || $female->status !== User::STATUS_NORMAL) {
                $skipped[] = '女号状态异常';

                continue;
            }

            $male = $this->pickMale($rule, $female, $males, $today);
            if (! $male instanceof User) {
                $skipped[] = '没有可用男号';

                continue;
            }
            if ($this->blockRepository->blockedEitherWay($female->id, $male->id)) {
                $skipped[] = '已被拉黑';

                continue;
            }

            $pair = $this->pairRepository->ensure((int) $rule->id, $male->id, $female->id, $today);
            if ((int) $pair->rounds_today >= $rule->round_limit_per_pair) {
                $skipped[] = '该男号今日轮数已满';

                continue;
            }
            if (! $this->intervalPassed($pair, $rule)) {
                $skipped[] = '未到发送间隔';

                continue;
            }

            try {
                if ($this->sendFromMale($rule, $target, $female, $male, $pair)) {
                    ++$sent;
                } else {
                    $skipped[] = '生成或落库失败';
                }
            } catch (Throwable $e) {
                $this->logger->warning('AI 互聊手动发送失败：' . $e->getMessage());
                $skipped[] = '发送异常';
            }
        }

        if ($sent === 0) {
            $reason = $skipped === [] ? '没有可发送的目标' : implode('、', array_unique($skipped));

            return $this->fail($reason);
        }

        return [
            'sent' => true,
            'message' => sprintf('已发送 %d 条', $sent),
            'reason' => $skipped === [] ? null : ('跳过：' . implode('、', array_unique($skipped))),
        ];
    }

    /**
     * @return array{sent: false, message: null, reason: string}
     */
    private function fail(string $reason): array
    {
        return ['sent' => false, 'message' => null, 'reason' => $reason];
    }

    /**
     * 女 AI 回复前问一句：这一对今天还能不能回。
     *
     * `AiChatService` 用它挡住超出轮数和超出上限的回复。
     */
    public function canFemaleReply(int $ruleId, int $maleId, int $femaleId, string $today): bool
    {
        $rule = $this->ruleRepository->findById($ruleId);
        if (! $rule instanceof BotChatRule) {
            return false;
        }

        $target = $this->targetRepository->findByRuleAndFemale($rule->id, $femaleId);
        if (! $target instanceof BotChatTarget) {
            return false;
        }

        // stat_date 不是今天说明还没重置过，按「可以」处理（下一轮 tick 会重置）
        if ((string) $target->stat_date !== $today) {
            return true;
        }
        if ($target->isDailyLimitReached()) {
            return false;
        }

        // 男号那条已经把轮数 +1 了，这里用 <= 让最后一轮的女号回复也能发出
        $rounds = $this->pairRepository->roundsForToday($rule->id, $maleId, $femaleId, $today);

        return $rounds <= $rule->round_limit_per_pair;
    }

    /**
     * 女 AI 回复成功后记一笔（只记日志，不重复计轮数和收益）。
     */
    public function recordFemaleReply(int $ruleId, int $maleId, int $femaleId, int $messageId): void
    {
        $this->logRepository->write([
            'rule_id' => $ruleId,
            'male_id' => $maleId,
            'female_id' => $femaleId,
            'message_id' => $messageId,
            'role' => BotChatLog::ROLE_FEMALE_AI,
            'virtual_points' => 0,
            'status' => BotChatLog::STATUS_SUCCESS,
        ]);
    }

    /**
     * 这条男号消息属于哪条规则（女 AI 回复时反查）。
     */
    public function ruleIdOfMessage(int $messageId): ?int
    {
        $log = $this->logRepository->findLatestByMessage($messageId);
        if ($log instanceof BotChatLog && $log->role === BotChatLog::ROLE_MALE_BOT) {
            return (int) $log->rule_id;
        }

        return null;
    }

    // ---------- 内部 ----------

    private function sendFromMale(
        BotChatRule $rule,
        BotChatTarget $target,
        User $female,
        User $male,
        BotChatPair $pair,
    ): bool {
        // 只为拿上下文而**只读**一次会话：生成失败时不能留下一条空会话，
        // 否则女号的消息列表里会多出一行没有任何消息的记录。
        $existing = $this->conversationRepository->findByPair($male->id, $female->id);

        $content = $this->generateContent($rule, $male, $female, $existing === null ? 0 : (int) $existing->id);
        if ($content === null) {
            $this->logFailure($rule, $male, $female, 'AI 生成失败');

            return false;
        }

        try {
            // 事务包住「建会话 → 写消息 → 改计数」：`lockOrCreate` 的 lockForUpdate
            // 只有在事务里才有意义，否则两个协程同时改未读数会丢一次 +1。
            /** @var Message $message */
            $message = Db::transaction(function () use ($rule, $target, $female, $male, $pair, $content): Message {
                $conversation = $this->conversationService->lockOrCreate($male, $female);
                $message = $this->writeMessage($male, $female, $conversation, $content);

                $sentAt = date('Y-m-d H:i:s');
                $points = $this->virtualPointsOf($female);

                $this->pairRepository->touch((int) $pair->id, $sentAt);
                $this->targetRepository->addVirtualPoints(
                    (int) $target->id,
                    $points,
                    $this->limitPoints($rule),
                    $sentAt,
                );

                $this->logRepository->write([
                    'rule_id' => (int) $rule->id,
                    'male_id' => $male->id,
                    'female_id' => $female->id,
                    'message_id' => (int) $message->id,
                    'role' => BotChatLog::ROLE_MALE_BOT,
                    'virtual_points' => $points,
                    'status' => BotChatLog::STATUS_SUCCESS,
                ]);

                return $message;
            });
        } catch (Throwable $e) {
            $this->logger->warning('AI 互聊发送失败：' . $e->getMessage());
            $this->logFailure($rule, $male, $female, mb_substr($e->getMessage(), 0, 200));

            return false;
        }

        // 推送：客户端回前台会靠 /chat/sync 补拉，推不出去不影响消息
        try {
            $this->pushService->messageReceived($message, $female);
        } catch (Throwable $e) {
            $this->logger->warning('AI 互聊推送失败：' . $e->getMessage());
        }

        // 女号开了 AI 代聊就让她回一条（异步，不阻塞）
        Coroutine::create(function () use ($message): void {
            try {
                $this->aiChatService->replyToBotMessage((int) $message->id);
            } catch (Throwable $e) {
                $this->logger->warning('AI 互聊女号回复失败：' . $e->getMessage());
            }
        });

        return true;
    }

    /**
     * 写一条机器人消息：不扣钱、不给积分，只写消息 + 会话。
     */
    private function writeMessage(User $male, User $female, mixed $conversation, string $content): Message
    {
        $sensitiveHit = $this->sensitiveWordService->hit($content);

        /** @var Message $message */
        $message = $this->messageRepository->create([
            'conversation_id' => (int) $conversation->id,
            'sender_id' => $male->id,
            'receiver_id' => $female->id,
            'client_msg_id' => sprintf('bot_%d_%s', $male->id, bin2hex(random_bytes(8))),
            'type' => Message::TYPE_TEXT,
            'content' => $content,
            'price_coins' => 0,
            'free_reason' => self::FREE_REASON_BOT_MALE,
            'receiver_level' => (int) ($female->level ?? 1),
            'share_rate' => $this->pricingService->shareRateOf($female, $this->pricingService->levelRateOf($female)),
            'receiver_points' => 0,
            'sensitive_hit' => $sensitiveHit ? 1 : 0,
            'review_status' => $sensitiveHit ? Message::REVIEW_PENDING : Message::REVIEW_NONE,
        ]);

        $this->conversationService->touch($conversation, $message, $male, $female);

        return $message;
    }

    /** 这条消息「如果按真实付费算」值多少积分 —— 只用于计数，不结算。 */
    private function virtualPointsOf(User $female): int
    {
        $rate = $this->pricingService->shareRateOf($female, $this->pricingService->levelRateOf($female));

        return $this->pricingService->pointsFor($this->pricingService->levelPriceOf($female), $rate);
    }

    /** 每日上限换算成积分：1500 分 × 100 积分/元 ÷ 100 = 1500 积分。 */
    private function limitPoints(BotChatRule $rule): int
    {
        $pointPerYuan = max(1, (int) $this->configService->get('point.per_yuan', 100));

        return intdiv($rule->daily_amount_limit_fen * $pointPerYuan, 100);
    }

    /**
     * 同一对之间是否已经隔够时间。
     *
     * 间隔带 ±30% 的固定抖动：真人不会每 10 秒准点发一条，整齐得像节拍器。
     * 抖动用 `last_sent_at` 算，不是每次随机 —— 否则同一秒里查两次会得到不同答案。
     */
    private function intervalPassed(BotChatPair $pair, BotChatRule $rule): bool
    {
        if ($pair->last_sent_at === null || $rule->interval_seconds <= 0) {
            return true;
        }

        $seed = crc32($pair->id . '|' . $pair->last_sent_at->getTimestamp()) % 601; // 0..600
        $jitter = (int) round($rule->interval_seconds * ($seed - 300) / 1000);       // ±30%
        $interval = max(1, $rule->interval_seconds + $jitter);

        return $pair->last_sent_at->getTimestamp() + $interval <= time();
    }

    /**
     * 解析这条规则实际可用的男号：手动指定的 + 从机器人号池随机补足。
     *
     * 「机器人号池」= 后台批量开号生成的男号（`cl_user.source = 2` 且 `batch_no` 非空）。
     *
     * @return array<int,User>
     */
    public function resolveMales(BotChatRule $rule): array
    {
        $ids = $rule->manualMaleIds();

        if ($rule->pool_male_count > 0) {
            $pool = $this->userRepository->getQuery()
                ->where('gender', User::GENDER_MALE)
                ->where('status', User::STATUS_NORMAL)
                ->where('source', 2)
                ->whereNotNull('batch_no')
                ->where('batch_no', '!=', '')
                ->when($ids !== [], fn ($q) => $q->whereNotIn('id', $ids))
                ->inRandomOrder()
                ->limit($rule->pool_male_count)
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            $ids = array_values(array_unique(array_merge($ids, $pool)));
        }

        if ($ids === []) {
            return [];
        }

        /** @var array<int,User> $users */
        $users = $this->userRepository->getQuery()
            ->whereIn('id', $ids)
            ->where('gender', User::GENDER_MALE)
            ->where('status', User::STATUS_NORMAL)
            ->get()
            ->all();

        return $users;
    }

    /**
     * 选一个男号。
     *
     * 像真人聊天，不是每条都换个人：
     * 1. **先延续当前这段对话** —— 上一个说话的人还没冷场（`session_gap_seconds` 内）、
     *    这一对的轮数还没用完，就继续由他接话；
     * 2. 冷场了才开新对话，优先挑今天还没跟这个女号聊过的，让更多号露面；
     * 3. 每个男号还受「每天最多发多少条」限制（`male_daily_limit`，0 = 不限）。
     *
     * @param array<int,User> $males
     */
    private function pickMale(BotChatRule $rule, User $female, array $males, string $today): ?User
    {
        $usable = [];
        foreach ($males as $male) {
            if (! $male->isMale() || $male->status !== User::STATUS_NORMAL) {
                continue;
            }
            if ($male->id === $female->id) {
                continue;
            }
            $usable[] = $male;
        }

        if ($usable === []) {
            return null;
        }

        // 男号每日总条数：一个号撩遍所有女号也会被拦住
        if ($rule->male_daily_limit > 0) {
            $counts = $this->pairRepository->maleDailyCounts((int) $rule->id, $today);
            $usable = array_values(array_filter(
                $usable,
                static fn (User $m): bool => ($counts[$m->id] ?? 0) < $rule->male_daily_limit,
            ));

            if ($usable === []) {
                return null;
            }
        }

        $byId = [];
        foreach ($usable as $male) {
            $byId[$male->id] = $male;
        }

        // ① 延续正在进行的对话
        $latest = $this->pairRepository->latestForFemale((int) $rule->id, $female->id, $today);
        if ($latest instanceof BotChatPair
            && isset($byId[(int) $latest->male_id])
            && (int) $latest->rounds_today < $rule->round_limit_per_pair
            && $latest->last_sent_at !== null
            && $latest->last_sent_at->getTimestamp() + max(1, $rule->session_gap_seconds) >= time()
        ) {
            return $byId[(int) $latest->male_id];
        }

        // ② 冷场了，开新对话
        $todayMales = $this->pairRepository->maleIdsToday((int) $rule->id, $female->id, $today);
        $fresh = array_values(array_filter($usable, static fn (User $m): bool => ! in_array($m->id, $todayMales, true)));

        $pool = $fresh !== [] ? $fresh : $usable;

        return $pool[array_rand($pool)];
    }

    /**
     * 生成男号要说的话：该会话没历史就开场白，有历史就带上下文接话。
     */
    private function generateContent(BotChatRule $rule, User $male, User $female, int $conversationId): ?string
    {
        $persona = $this->personaRepository->findByUser($male->id);

        $system = $this->maleSystemPrompt($male, $persona);
        $history = $this->messageRepository->historyOf($conversationId, null, $rule->context_messages);

        $messages = [['role' => 'system', 'content' => $system]];

        if ($history->isEmpty()) {
            $messages[] = ['role' => 'user', 'content' => sprintf(
                '对方资料：%s，%s岁，城市%s，职业%s。请主动发第一句话。',
                $female->nickname,
                (string) $female->age(),
                (string) $female->city,
                (string) $female->job,
            )];
        } else {
            // historyOf 是最新在前，喂模型前要转回时间正序
            foreach (array_reverse($history->all()) as $row) {
                if ($row->type !== Message::TYPE_TEXT || trim((string) $row->content) === '') {
                    continue;
                }
                $messages[] = [
                    'role' => $row->sender_id === $male->id ? 'assistant' : 'user',
                    'content' => (string) $row->content,
                ];
            }
            $messages[] = ['role' => 'user', 'content' => '接着上面的话，自然地说一句。'];
        }

        $result = $this->client->chat($messages);
        if ($result === null || trim($result['content']) === '') {
            return null;
        }

        if ($persona instanceof AiPersona) {
            $this->personaRepository->recordUsage(
                (int) $persona->id,
                $result['prompt_tokens'],
                $result['completion_tokens'],
                date('Y-m-d'),
            );
        }

        return trim($result['content']);
    }

    private function maleSystemPrompt(User $male, ?AiPersona $persona): string
    {
        $lines = [];
        $lines[] = sprintf(
            '你在扮演一个真实的男用户：昵称%s，%s岁，城市%s，职业%s。',
            $male->nickname,
            (string) $male->age(),
            (string) $male->city,
            (string) $male->job,
        );
        if ($persona instanceof AiPersona && $persona->persona !== '') {
            $lines[] = '人设：' . $persona->persona;
        }
        if ($persona instanceof AiPersona && $persona->tone !== '') {
            $lines[] = '说话风格：' . $persona->tone;
        }
        if ($persona instanceof AiPersona && $persona->taboos !== '') {
            $lines[] = '这些话题不聊，被提到就轻轻带过：' . $persona->taboos;
        }

        $lines[] = '聊天规则：';
        $lines[] = '- 一条消息 5~25 个字，像微信聊天一样短';
        $lines[] = '- 不要分点、不要总结、不要给建议、不要用书面语';
        $lines[] = '- 不主动提钱、不主动要联系方式、不说自己是 AI 或机器人';
        $lines[] = '- 不要重复前面已经说过的话';

        if ($persona instanceof AiPersona && (string) ($persona->examples ?? '') !== '') {
            $lines[] = '';
            $lines[] = '你平时聊天是这样的（照着这个语气和长度）：';
            $lines[] = (string) $persona->examples;
        }

        return implode("\n", $lines);
    }

    private function logFailure(BotChatRule $rule, User $male, User $female, string $reason): void
    {
        $this->logRepository->write([
            'rule_id' => (int) $rule->id,
            'male_id' => $male->id,
            'female_id' => $female->id,
            'message_id' => null,
            'role' => BotChatLog::ROLE_MALE_BOT,
            'virtual_points' => 0,
            'status' => BotChatLog::STATUS_FAILED,
            'fail_reason' => $reason,
        ]);
    }
}
