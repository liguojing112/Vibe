<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 热门城市（docs/04 第 1 节）。
 *
 * @property int $id
 * @property string $name
 * @property int $sort
 * @property int $enabled
 */
final class City extends MineModel
{
    protected ?string $table = 'cl_city';

    protected array $fillable = ['id', 'name', 'sort', 'enabled', 'created_at', 'updated_at'];

    protected array $casts = ['id' => 'integer', 'sort' => 'integer', 'enabled' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];
}
