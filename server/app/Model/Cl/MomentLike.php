<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 朋友圈点赞。`(moment_id, user_id)` 唯一，重复点赞靠唯一键挡住（幂等）。
 *
 * @property int $id
 * @property int $moment_id
 * @property int $user_id
 */
class MomentLike extends MineModel
{
    protected ?string $table = 'cl_moment_like';

    protected array $fillable = ['id', 'moment_id', 'user_id', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'moment_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
