<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 女用户积分钱包（docs/04 第 4 节）。
 *
 * @property int $id
 * @property int $user_id
 * @property int $balance
 * @property int $frozen
 * @property int $total_withdrawn
 */
final class PointWallet extends MineModel
{
    protected ?string $table = 'cl_point_wallet';

    protected array $fillable = ['id', 'user_id', 'balance', 'frozen', 'total_withdrawn', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'balance' => 'integer',
        'frozen' => 'integer',
        'total_withdrawn' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
