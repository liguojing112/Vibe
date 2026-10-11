<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 男用户金币钱包（docs/04 第 4 节）。
 *
 * @property int $id
 * @property int $user_id
 * @property int $balance
 * @property int $total_recharge
 * @property int $total_spent
 */
final class CoinWallet extends MineModel
{
    protected ?string $table = 'cl_coin_wallet';

    protected array $fillable = ['id', 'user_id', 'balance', 'total_recharge', 'total_spent', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'balance' => 'integer',
        'total_recharge' => 'integer',
        'total_spent' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
