<?php

declare(strict_types=1);

namespace App\Repository\Cl;

use App\Model\Cl\BotChatPair;
use App\Repository\IRepository;
use Hyperf\Database\Exception\UniqueConstraintViolationException;
use Hyperf\DbConnection\Db;
use RuntimeException;

/**
 * @extends IRepository<BotChatPair>
 */
final class BotChatPairRepository extends IRepository
{
    public function __construct(
        protected readonly BotChatPair $model
    ) {}

    public function findForToday(int $ruleId, int $maleId, int $femaleId, string $today): ?BotChatPair
    {
        /** @var null|BotChatPair $row */
        $row = $this->model->newQuery()
            ->where('rule_id', $ruleId)
            ->where('male_id', $maleId)
            ->where('female_id', $femaleId)
            ->where('stat_date', $today)
            ->first();

        return $row;
    }

    /** 没有就建一条今天的状态行。 */
    public function ensure(int $ruleId, int $maleId, int $femaleId, string $today): BotChatPair
    {
        $row = $this->findForToday($ruleId, $maleId, $femaleId, $today);
        if ($row instanceof BotChatPair) {
            return $row;
        }

        try {
            /** @var BotChatPair $created */
            $created = $this->model->newQuery()->create([
                'rule_id' => $ruleId,
                'male_id' => $maleId,
                'female_id' => $femaleId,
                'stat_date' => $today,
                'rounds_today' => 0,
            ]);

            return $created;
        } catch (UniqueConstraintViolationException) {
            // 并发下另一个协程刚建好，取它
            $row = $this->findForToday($ruleId, $maleId, $femaleId, $today);
            if ($row instanceof BotChatPair) {
                return $row;
            }

            throw new RuntimeException('AI 互聊：创建配对状态失败');
        }
    }

    public function touch(int $pairId, string $sentAt): void
    {
        $this->model->newQuery()->whereKey($pairId)->update([
            'rounds_today' => Db::raw('rounds_today + 1'),
            'last_sent_at' => $sentAt,
        ]);
    }

    /** 某一对当天的轮数（女 AI 回复前要查）。 */
    public function roundsForToday(int $ruleId, int $maleId, int $femaleId, string $today): int
    {
        $row = $this->findForToday($ruleId, $maleId, $femaleId, $today);

        return $row instanceof BotChatPair ? (int) $row->rounds_today : 0;
    }

    /**
     * 今天已经和这个女号聊过的男号 id（选男号时优先避开，让更多男号露面）。
     *
     * @return array<int,int>
     */
    public function maleIdsToday(int $ruleId, int $femaleId, string $today): array
    {
        return $this->model->newQuery()
            ->where('rule_id', $ruleId)
            ->where('female_id', $femaleId)
            ->where('stat_date', $today)
            ->pluck('male_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
