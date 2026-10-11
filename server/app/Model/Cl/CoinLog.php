<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 金币流水（docs/04 第 4 节）。
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $change
 * @property int $balance_after
 * @property string $ref_type
 * @property int $ref_id
 * @property string $title
 */
final class CoinLog extends MineModel
{
    protected ?string $table = 'cl_coin_log';

    protected array $fillable = ['id', 'user_id', 'type', 'change', 'balance_after', 'ref_type', 'ref_id', 'title', 'remark', 'operator_id', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'user_id' => 'integer', 'change' => 'integer', 'balance_after' => 'integer', 'ref_id' => 'integer', 'operator_id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
