<?php

declare(strict_types=1);

use App\Model\Cl\Gift;
use Hyperf\Database\Seeders\Seeder;

/**
 * 礼物默认值（docs/04 第 5 节）。
 *
 * 图标用 emoji：docs/04 允许「图片 URL 或 emoji」，emoji 不用传图也不用审核。
 */
class ClGiftSeeder20261013 extends Seeder
{
    public const DATA = [
        ['name' => '一朵玫瑰', 'icon' => '🌹', 'price_coins' => 10, 'sort' => 1],
        ['name' => '热拿铁', 'icon' => '☕', 'price_coins' => 30, 'sort' => 2],
        ['name' => '心动气球', 'icon' => '🎈', 'price_coins' => 60, 'sort' => 3],
        ['name' => '浪漫花束', 'icon' => '💐', 'price_coins' => 120, 'sort' => 4],
        ['name' => '星河许愿', 'icon' => '🌠', 'price_coins' => 300, 'sort' => 5],
        ['name' => '心动城堡', 'icon' => '🏰', 'price_coins' => 680, 'sort' => 6],
    ];

    public function run(): void
    {
        Gift::truncate();
        foreach (self::DATA as $row) {
            Gift::create(array_merge($row, ['enabled' => 1]));
        }
    }
}
