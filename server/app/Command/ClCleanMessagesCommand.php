<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Cl\Chat\MessageRetentionService;
use Hyperf\Command\Annotation\Command as CommandAnnotation;
use Hyperf\Command\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * 手动跑一次私信留存清理（定时任务在 `app/Crontab/CleanExpiredMessagesCrontab.php`）。
 *
 * ```bash
 * php bin/hyperf.php cl:clean-messages            # 按后台配置的月数
 * php bin/hyperf.php cl:clean-messages --months=1 # 临时按 1 个月试一把
 * ```
 */
#[CommandAnnotation]
final class ClCleanMessagesCommand extends Command
{
    protected ?string $name = 'cl:clean-messages';

    protected string $description = '清理超过保留期的私信（保留敏感词命中与被举报的消息）';

    public function __construct(
        private readonly MessageRetentionService $retentionService,
    ) {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->addOption('months', null, InputOption::VALUE_REQUIRED, '覆盖保留月数（不传就读 chat.message_retention_months）');
    }

    public function handle(): int
    {
        $months = $this->input->getOption('months');
        $months = $months === null ? null : (int) $months;

        $result = $this->retentionService->cleanup($months);

        $this->output->table(
            ['保留月数', '截止时间', '删除条数', '修正会话数', '批次数'],
            [[$result['months'], $result['cutoff'] ?? '-', $result['deleted'], $result['conversations'], $result['batches']]],
        );

        if ($result['months'] <= 0) {
            $this->output->note('保留月数是 0，按「永久保留」处理，什么都没删。');
        }

        return self::SUCCESS;
    }
}
