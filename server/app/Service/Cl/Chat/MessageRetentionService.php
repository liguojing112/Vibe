<?php

declare(strict_types=1);

namespace App\Service\Cl\Chat;

use App\Repository\Cl\ConversationRepository;
use App\Repository\Cl\MessageRepository;
use App\Service\Cl\Config\AppConfigService;
use Hyperf\Carbon\Carbon;

/**
 * 私信留存与清理（docs/04 第 3 节、docs/06 第 2.1 节）。
 *
 * **为什么服务端要留消息**：App 端的聊天记录以本地库为主（docs/10），服务端这份只服务于
 * 监管与举报取证 —— 所以它不需要永久保留，超期就物理删掉，省空间也少一份数据风险。
 *
 * 三条规则（一条都不能少）：
 * 1. 只删**普通消息**（文字/图片）：礼物消息和系统消息牵着钱和通知，留着；
 * 2. **敏感词命中的不删**（`sensitive_hit = 1`）—— 那是待审/已判违规的证据；
 * 3. **被举报关联的不删**（`cl_report.message_id` 指到它就长期保留）。
 *
 * 删完还要**把会话的 `last_message_id` 补回来**：它可能正好指着被删的那条，
 * 不修的话会话列表会显示一条已经不存在的消息。
 */
final class MessageRetentionService
{
    /** 后台没配时的默认保留月数（docs/06 第 2.1 节）。 */
    public const DEFAULT_MONTHS = 6;

    /** 一批删多少条。分批是为了别让一次删除锁住整张表、也别让定时任务跑太久。 */
    private const BATCH_SIZE = 500;

    /** 一次最多删几批（500 × 200 = 10 万条），剩下的下一轮再删。 */
    private const MAX_BATCHES = 200;

    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly AppConfigService $configService,
    ) {}

    /** 后台配的保留月数；**0 表示不清理**（想永久保留就把后台那个值改成 0）。 */
    public function retentionMonths(): int
    {
        return max(0, (int) $this->configService->get('chat.message_retention_months', self::DEFAULT_MONTHS));
    }

    /**
     * 跑一次清理。
     *
     * @param null|int $months 覆盖保留月数（调试/测试用；不传就读配置）
     * @return array{months: int, cutoff: null|string, deleted: int, conversations: int, batches: int}
     */
    public function cleanup(?int $months = null): array
    {
        $months ??= $this->retentionMonths();
        if ($months <= 0) {
            return ['months' => $months, 'cutoff' => null, 'deleted' => 0, 'conversations' => 0, 'batches' => 0];
        }

        $cutoff = Carbon::now()->subMonths($months)->toDateTimeString();
        $deleted = 0;
        $batches = 0;
        $conversationIds = [];

        while ($batches < self::MAX_BATCHES) {
            $ids = $this->messageRepository->expiredIds($cutoff, self::BATCH_SIZE);
            if ($ids === []) {
                break;
            }
            ++$batches;

            $conversationIds = array_merge($conversationIds, $this->messageRepository->conversationIdsOf($ids));
            $deleted += $this->messageRepository->deleteByIds($ids);
        }

        $conversationIds = array_values(array_unique($conversationIds));
        $this->conversationRepository->refreshLastMessageIds($conversationIds);

        return [
            'months' => $months,
            'cutoff' => $cutoff,
            'deleted' => $deleted,
            'conversations' => count($conversationIds),
            'batches' => $batches,
        ];
    }
}
