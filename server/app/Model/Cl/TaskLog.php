<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 领奖流水（docs/04 第 5 节）。唯一索引 `(user_id, task_id, period_key)` ——
 * **同一周期只能领一次**，并发领两次也只发一次（撞唯一索引的那次事务回滚）。
 *
 * @property int $id
 * @property int $user_id
 * @property int $task_id
 * @property string $period_key
 * @property int $reward
 * @property int $reward_type
 */
class TaskLog extends MineModel
{
    /** 奖励单位：金币（男）。 */
    public const REWARD_COIN = 1;

    /** 奖励单位：积分（女）。 */
    public const REWARD_POINT = 2;

    /** 一次性任务的周期键（每日任务是 `yyyyMMdd`）。 */
    public const PERIOD_ONCE_KEY = 'ONCE';

    protected ?string $table = 'cl_task_log';

    protected array $attributes = [
        'reward' => 0,
        'reward_type' => self::REWARD_COIN,
    ];

    protected array $fillable = [
        'id', 'user_id', 'task_id', 'period_key', 'reward', 'reward_type', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'task_id' => 'integer',
        'reward' => 'integer',
        'reward_type' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
