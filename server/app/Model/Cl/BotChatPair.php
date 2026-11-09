<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * AI 互聊：每对男女当日的轮数与最后发送时间。
 *
 * 一行代表「这一对在今天已经聊了几个来回」，跨天靠 `stat_date` 换行重置。
 *
 * @property int $id
 * @property int $rule_id
 * @property int $male_id
 * @property int $female_id
 * @property string $stat_date
 * @property int $rounds_today
 * @property null|\Carbon\Carbon $last_sent_at
 */
class BotChatPair extends MineModel
{
    protected ?string $table = 'cl_bot_chat_pair';

    protected array $attributes = [
        'rounds_today' => 0,
    ];

    protected array $fillable = [
        'id', 'rule_id', 'male_id', 'female_id', 'stat_date', 'rounds_today',
        'last_sent_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'rule_id' => 'integer',
        'male_id' => 'integer',
        'female_id' => 'integer',
        'rounds_today' => 'integer',
        'last_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
