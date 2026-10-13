<?php

declare(strict_types=1);

use App\Model\Cl\VipPlan;
use Hyperf\Database\Seeders\Seeder;

/**
 * 会员套餐默认值（docs/06 第 2.4 节）：月卡 390 / 季卡 990 / 年卡 2990 金币。
 */
class ClVipPlanSeeder20261013 extends Seeder
{
    public const DATA = [
        ['name' => '月卡', 'days' => 30, 'price_coins' => 390, 'sort' => 1],
        ['name' => '季卡', 'days' => 90, 'price_coins' => 990, 'sort' => 2],
        ['name' => '年卡', 'days' => 365, 'price_coins' => 2990, 'sort' => 3],
    ];

    public function run(): void
    {
        VipPlan::truncate();
        foreach (self::DATA as $row) {
            VipPlan::create(array_merge($row, ['enabled' => 1]));
        }
    }
}
