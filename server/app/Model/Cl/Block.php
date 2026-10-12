<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 拉黑（docs/04 第 2 节）。A 拉黑 B 后双方在发现页互相看不到，B 也不能给 A 发消息。
 */
class Block extends MineModel
{
    protected ?string $table = 'cl_block';

    protected array $fillable = ['id', 'user_id', 'target_id', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'target_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
