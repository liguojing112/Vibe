<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 后台批量开号批次（docs/04 第 7 节）。
 *
 * @property int $id
 * @property string $batch_no
 * @property int $gender
 * @property int $count
 * @property string $account_prefix
 * @property null|int $operator_id
 * @property null|string $remark
 * @property int $exported
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 */
final class BatchAccount extends MineModel
{
    protected ?string $table = 'cl_batch_account';

    protected array $fillable = [
        'id', 'batch_no', 'gender', 'count', 'account_prefix',
        'operator_id', 'remark', 'exported', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'gender' => 'integer',
        'count' => 'integer',
        'operator_id' => 'integer',
        'exported' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
