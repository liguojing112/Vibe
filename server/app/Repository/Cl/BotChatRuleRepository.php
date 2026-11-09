<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\BotChatRule;
use App\Repository\IRepository;

/**
 * @extends IRepository<BotChatRule>
 */
final class BotChatRuleRepository extends IRepository
{
    public function __construct(
        protected readonly BotChatRule $model
    ) {}

    /**
     * 启用中的规则（定时任务每一轮取这个）。
     *
     * @return array<int,BotChatRule>
     */
    public function listEnabled(): array
    {
        /** @var array<int,BotChatRule> $rows */
        $rows = $this->model->newQuery()
            ->where('enabled', BotChatRule::STATUS_RUNNING)
            ->orderBy('id')
            ->get()
            ->all();

        return $rows;
    }
}
