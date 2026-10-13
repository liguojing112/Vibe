<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 礼物（docs/04 第 5 节）。价格由后台配，客户端只显示。
 *
 * @property int $id
 * @property string $name
 * @property string $icon
 * @property int $price_coins
 * @property int $sort
 * @property int $enabled
 */
class Gift extends MineModel
{
    protected ?string $table = 'cl_gift';

    protected array $attributes = [
        'price_coins' => 0,
        'sort' => 0,
        'enabled' => 1,
    ];

    protected array $fillable = ['id', 'name', 'icon', 'price_coins', 'sort', 'enabled', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'price_coins' => 'integer',
        'sort' => 'integer',
        'enabled' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isEnabled(): bool
    {
        return $this->enabled === 1;
    }
}
