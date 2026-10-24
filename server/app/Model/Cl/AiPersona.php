<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * AI 代聊人设（一个女用户最多一套）。
 *
 * @property int $id
 * @property int $user_id
 * @property int $enabled
 * @property string $persona
 * @property string $tone
 * @property string $taboos
 * @property int $proactive
 * @property int $active_start
 * @property int $active_end
 * @property int $daily_limit
 * @property int $replied_today
 * @property null|string $replied_on
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $status
 */
class AiPersona extends MineModel
{
    public const STATUS_NORMAL = 1;

    public const STATUS_PAUSED = 2;

    /** 主动搭话强度：0 关闭 / 1 低 / 2 中 / 3 高。 */
    public const PROACTIVE_OFF = 0;

    public const PROACTIVE_LOW = 1;

    public const PROACTIVE_MID = 2;

    public const PROACTIVE_HIGH = 3;

    protected ?string $table = 'cl_ai_persona';

    protected array $fillable = [
        'id', 'user_id', 'enabled', 'persona', 'tone', 'taboos', 'proactive',
        'active_start', 'active_end', 'daily_limit', 'replied_today', 'replied_on',
        'prompt_tokens', 'completion_tokens', 'status', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'enabled' => 'integer',
        'proactive' => 'integer',
        'active_start' => 'integer',
        'active_end' => 'integer',
        'daily_limit' => 'integer',
        'replied_today' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
