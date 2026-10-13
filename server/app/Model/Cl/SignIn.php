<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 签到流水（docs/04 第 5 节）。唯一索引 `(user_id, sign_date)` —— 一天只能签一次。
 *
 * 单独一张表（而不是复用 `cl_task_log`）是为了以后算**连续签到**：
 * 设计图 11 上写着「连续签到可领取玫瑰」，那时候要按日期回溯，从领奖流水里推不方便。
 *
 * @property int $id
 * @property int $user_id
 * @property \Carbon\Carbon $sign_date
 */
class SignIn extends MineModel
{
    protected ?string $table = 'cl_sign_in';

    protected array $fillable = ['id', 'user_id', 'sign_date', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'sign_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
