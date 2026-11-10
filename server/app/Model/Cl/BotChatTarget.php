<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * AI 互聊的目标女号 + 当日虚拟收益。
 *
 * `virtual_points_today` **只是计数**：它决定今天还能不能继续聊，
 * 不会进积分钱包，女号提现、等级、累计收益都不受影响。
 *
 * @property int $id
 * @property int $rule_id
 * @property int $female_id
 * @property int $enabled
 * @property null|string $stat_date
 * @property int $virtual_points_today
 * @property int $rounds_today
 * @property null|string $stopped_reason
 * @property null|\Carbon\Carbon $last_sent_at
 */
class BotChatTarget extends MineModel
{
    protected ?string $table = 'cl_bot_chat_target';

    protected array $attributes = [
        'enabled' => 1,
        'virtual_points_today' => 0,
        'rounds_today' => 0,
    ];

    protected array $fillable = [
        'id', 'rule_id', 'female_id', 'enabled', 'stat_date', 'virtual_points_today',
        'rounds_today', 'stopped_reason', 'last_sent_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'rule_id' => 'integer',
        'female_id' => 'integer',
        'enabled' => 'integer',
        'virtual_points_today' => 'integer',
        'rounds_today' => 'integer',
        'last_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

}
