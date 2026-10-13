<?php

declare(strict_types=1);

namespace App\Crontab;

use App\Service\Cl\Chat\MessageRetentionService;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;

/**
 * 每天凌晨 4 点清理超期私信（docs/04 第 3 节）。
 *
 * 为什么放凌晨：删除是物理删除、分批跑，挑业务低峰期，避免和发消息抢行锁。
 * 保留月数从 `chat.message_retention_months` 读（后台可改，默认 6 个月；填 0 = 永久保留）。
 *
 * 想手动跑一次（或换个月数试）用命令：`php bin/hyperf.php cl:clean-messages`。
 */
final class CleanExpiredMessagesCrontab
{
    public function __construct(
        private readonly MessageRetentionService $retentionService,
        private readonly StdoutLoggerInterface $logger,
    ) {}

    #[Crontab(
        rule: '0 4 * * *',
        name: 'cl-clean-messages',
        callback: 'execute',
        memo: '清理超期私信（保留敏感词命中与被举报的）',
    )]
    public function execute(): void
    {
        $result = $this->retentionService->cleanup();

        $this->logger->info(sprintf(
            '[crontab] 私信清理完成：保留 %d 个月（%s 之前），删除 %d 条，修正 %d 个会话的最后一条消息',
            $result['months'],
            $result['cutoff'] ?? '-',
            $result['deleted'],
            $result['conversations'],
        ));
    }
}
