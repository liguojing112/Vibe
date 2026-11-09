<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\BotChatLog;
use App\Repository\IRepository;

/**
 * @extends IRepository<BotChatLog>
 */
final class BotChatLogRepository extends IRepository
{
    public function __construct(
        protected readonly BotChatLog $model
    ) {}

    public function write(array $data): void
    {
        $this->model->newQuery()->create($data);
    }

    /** 反查：这条消息是不是机器人规则发的（女 AI 回复时用来找规则）。 */
    public function findLatestByMessage(int $messageId): ?BotChatLog
    {
        /** @var null|BotChatLog $row */
        $row = $this->model->newQuery()->where('message_id', $messageId)->first();

        return $row;
    }
}
