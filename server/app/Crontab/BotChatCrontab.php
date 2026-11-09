<?php

declare(strict_types=1);

namespace App\Crontab;

use App\Model\Cl\BotChatRule;
use App\Model\Cl\BotChatTarget;
use App\Model\Cl\User;
use App\Repository\Cl\BotChatRuleRepository;
use App\Repository\Cl\BotChatTargetRepository;
use App\Repository\Cl\UserRepository;
use App\Service\Cl\Ai\BotChatService;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * AI 男女互聊：机器人男号给指定女号发消息（每分钟一轮）。
 *
 * 三条纪律：
 * 1. **全局开关优先**：`bot_chat.enabled` 关掉，一条都不发；
 * 2. **每轮限量**：单轮最多处理 `BATCH` 个目标女号，避免一次跑太久把调度器压住；
 * 3. **加锁**：多 worker 下同一时间只允许一个协程执行，否则同一对会连发两条。
 *
 * 节奏（轮数 / 间隔 / 时段 / 上限）全部来自规则本身，这里不写死任何业务数字。
 */
final class BotChatCrontab
{
    /** 单轮最多处理多少个目标女号。 */
    private const BATCH = 10;

    private const LOCK_KEY = 'bot_chat:crontab_lock';

    public function __construct(
        private readonly BotChatRuleRepository $ruleRepository,
        private readonly BotChatTargetRepository $targetRepository,
        private readonly UserRepository $userRepository,
        private readonly BotChatService $botChatService,
        private readonly Redis $redis,
        private readonly LoggerInterface $logger,
    ) {}

    #[Crontab(
        rule: '* * * * *',
        name: 'cl-bot-chat',
        callback: 'execute',
        memo: 'AI 男女互聊：机器人男号按规则给指定女号发消息',
    )]
    public function execute(): void
    {
        if (! $this->botChatService->enabled()) {
            return;
        }

        // 上一轮还没跑完就跳过这一轮（宁可慢一点，也不要同一对连发）
        if (! $this->redis->set(self::LOCK_KEY, (string) time(), ['NX', 'EX' => 240])) {
            return;
        }

        try {
            $this->run();
        } catch (Throwable $e) {
            $this->logger->error('AI 互聊定时任务失败：' . $e->getMessage());
        } finally {
            $this->redis->del(self::LOCK_KEY);
        }
    }

    private function run(): void
    {
        $today = date('Y-m-d');
        $budget = self::BATCH;

        foreach ($this->ruleRepository->listEnabled() as $rule) {
            if ($budget <= 0) {
                return;
            }
            if (! $rule->inActiveWindow()) {
                continue;
            }

            $males = $this->resolveMales($rule);
            if ($males === []) {
                continue;
            }

            foreach ($this->targetRepository->listActiveByRule((int) $rule->id) as $target) {
                if ($budget <= 0) {
                    return;
                }
                --$budget;

                try {
                    $this->botChatService->tick($rule, $target, $males, $today);
                } catch (Throwable $e) {
                    $this->logger->warning(sprintf(
                        'AI 互聊规则[%d] 女号[%d] 处理失败：%s',
                        $rule->id,
                        $target->female_id,
                        $e->getMessage(),
                    ));
                }
            }
        }
    }

    /**
     * 解析这条规则实际可用的男号：手动指定的 + 从机器人号池随机补足。
     *
     * 「机器人号池」= 后台批量开号生成的男号（`cl_user.source = 2` 且 `batch_no` 非空）。
     *
     * @return array<int,User>
     */
    private function resolveMales(BotChatRule $rule): array
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
}
