<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * AI 互聊发送记录。
 *
 * 每条实际发出去的消息都要留一行：出问题时要能回答「哪一对、谁发的、为什么没发成」。
 *
 * @property int $id
 * @property int $rule_id
 * @property int $male_id
 * @property int $female_id
 * @property null|int $message_id
 * @property string $role
 * @property int $virtual_points
 * @property int $status
 * @property null|string $fail_reason
 */
class BotChatLog extends MineModel
{
    public const ROLE_MALE_BOT = 'male_bot';

    public const ROLE_FEMALE_AI = 'female_ai';

    public const STATUS_SUCCESS = 1;

    public const STATUS_FAILED = 2;

    protected ?string $table = 'cl_bot_chat_log';

    protected array $attributes = [
        'role' => self::ROLE_MALE_BOT,
        'virtual_points' => 0,
        'status' => self::STATUS_SUCCESS,
    ];

    protected array $fillable = [
        'id', 'rule_id', 'male_id', 'female_id', 'message_id', 'role',
        'virtual_points', 'status', 'fail_reason', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'rule_id' => 'integer',
        'male_id' => 'integer',
        'female_id' => 'integer',
        'message_id' => 'integer',
        'virtual_points' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
