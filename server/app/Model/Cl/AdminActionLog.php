<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 后台对某个用户的动作流水（docs/07 第 2 节的封禁记录、docs/06 第 5 节的调等级日志）。
 *
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string $detail
 * @property null|int $operator_id
 * @property string $operator_name
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 */
final class AdminActionLog extends MineModel
{
    public const ACTION_BAN = 'BAN';

    public const ACTION_UNBAN = 'UNBAN';

    public const ACTION_LEVEL = 'LEVEL';

    public const ACTION_VIP = 'VIP';

    public const ACTION_GENDER = 'GENDER';

    public const ACTION_PROFILE = 'PROFILE';

    public const ACTION_PASSWORD = 'PASSWORD';

    /** 后台扣金币 / 调积分（docs/06 第 8 节，只有超管能做）。 */
    public const ACTION_COIN_DEDUCT = 'COIN_DEDUCT';

    public const ACTION_POINT_ADJUST = 'POINT_ADJUST';

    protected ?string $table = 'cl_admin_action_log';

    protected array $fillable = [
        'id', 'user_id', 'action', 'detail', 'operator_id', 'operator_name', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
