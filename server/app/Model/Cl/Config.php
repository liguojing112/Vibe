<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 运营配置表（docs/04 第 6 节）。
 *
 * @property int $id
 * @property string $key
 * @property null|string $value
 * @property string $value_type
 * @property string $group
 * @property string $label
 * @property string $remark
 */
final class Config extends MineModel
{
    protected ?string $table = 'cl_config';

    protected array $fillable = ['id', 'key', 'value', 'value_type', 'group', 'label', 'remark', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
