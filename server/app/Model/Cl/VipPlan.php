<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 会员套餐（docs/04 第 5 节）。天数和价格都由后台配，客户端只显示。
 *
 * @property int $id
 * @property string $name
 * @property int $days
 * @property int $price_coins
 * @property int $sort
 * @property int $enabled
 */
class VipPlan extends MineModel
{
    protected ?string $table = 'cl_vip_plan';

    protected array $attributes = [
        'days' => 0,
        'price_coins' => 0,
        'sort' => 0,
        'enabled' => 1,
    ];

    protected array $fillable = ['id', 'name', 'days', 'price_coins', 'sort', 'enabled', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'days' => 'integer',
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
