<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 平台收入明细（docs/04 第 4 节）。
 *
 * @property int $id
 * @property string $source
 * @property int $ref_id
 * @property int $payer_id
 * @property null|int $receiver_id
 * @property int $coins
 * @property int $receiver_points
 * @property string $income_date
 */
final class PlatformIncome extends MineModel
{
    protected ?string $table = 'cl_platform_income';

    protected array $fillable = ['id', 'source', 'ref_id', 'payer_id', 'receiver_id', 'coins', 'receiver_points', 'income_date', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'ref_id' => 'integer', 'payer_id' => 'integer', 'receiver_id' => 'integer', 'coins' => 'integer', 'receiver_points' => 'integer', 'income_date' => 'date', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
