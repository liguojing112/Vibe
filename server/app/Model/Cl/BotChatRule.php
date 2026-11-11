<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * AI 男女互聊规则：机器人男号给指定女号发消息。
 *
 * 节奏参数全部按规则配，代码里不写死业务数字：轮数、间隔、时段、每日收益上限。
 *
 * @property int $id
 * @property string $name
 * @property int $enabled
 * @property int $auto_send
 * @property array<int,int> $male_user_ids
 * @property int $pool_male_count
 * @property int $window_start
 * @property int $window_end
 * @property int $round_limit_per_pair
 * @property int $male_daily_limit
 * @property int $interval_seconds
 * @property int $interval_max_seconds
 * @property int $session_gap_seconds
 * @property int $daily_amount_limit_fen
 * @property int $context_messages
 * @property null|int $operator_id
 */
class BotChatRule extends MineModel
{
    public const STATUS_STOPPED = 0;

    public const STATUS_RUNNING = 1;

    /** 定时任务自动发（原来的行为）。 */
    public const AUTO_SEND_ON = 1;

    /** 只在后台点「发送」时发一次。 */
    public const AUTO_SEND_OFF = 0;

    protected ?string $table = 'cl_bot_chat_rule';

    protected array $attributes = [
        'enabled' => 0,
        'auto_send' => 1,
        'pool_male_count' => 0,
        'window_start' => 0,
        'window_end' => 0,
        'round_limit_per_pair' => 20,
        'male_daily_limit' => 0,
        'interval_seconds' => 30,
        'interval_max_seconds' => 0,
        'session_gap_seconds' => 300,
        'daily_amount_limit_fen' => 1500,
        'context_messages' => 10,
    ];

    protected array $fillable = [
        'id', 'name', 'enabled', 'auto_send', 'male_user_ids', 'pool_male_count',
        'window_start', 'window_end', 'round_limit_per_pair', 'male_daily_limit',
        'interval_seconds', 'interval_max_seconds', 'session_gap_seconds',
        'daily_amount_limit_fen', 'context_messages', 'operator_id',
        'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'enabled' => 'integer',
        'auto_send' => 'integer',
        'male_user_ids' => 'array',
        'pool_male_count' => 'integer',
        'window_start' => 'integer',
        'window_end' => 'integer',
        'round_limit_per_pair' => 'integer',
        'male_daily_limit' => 'integer',
        'interval_seconds' => 'integer',
        'interval_max_seconds' => 'integer',
        'session_gap_seconds' => 'integer',
        'daily_amount_limit_fen' => 'integer',
        'context_messages' => 'integer',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** 现在是否在活跃时段内。两个值相同表示全天。跨零点（22 → 2）也支持。 */
    public function inActiveWindow(?int $hour = null): bool
    {
        $hour ??= (int) date('G');
        $start = $this->window_start;
        $end = $this->window_end;

        if ($start === $end) {
            return true;
        }

        return $start < $end
            ? ($hour >= $start && $hour < $end)
            : ($hour >= $start || $hour < $end);
    }

    /**
     * 手动指定的男号 id（去重、只留正整数）。
     *
     * @return array<int,int>
     */
    public function manualMaleIds(): array
    {
        $ids = $this->male_user_ids ?? [];

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }
}
