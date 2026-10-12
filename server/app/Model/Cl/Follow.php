<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 关注（docs/04 第 2 节）。双方都关注 = 互关（好友）。
 */
class Follow extends MineModel
{
    protected ?string $table = 'cl_follow';

    protected array $fillable = ['id', 'user_id', 'target_id', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'target_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
