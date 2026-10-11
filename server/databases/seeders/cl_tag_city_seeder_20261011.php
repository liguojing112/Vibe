<?php

declare(strict_types=1);

use App\Model\Cl\City;
use App\Model\Cl\Tag;
use Hyperf\Database\Seeders\Seeder;

/**
 * 兴趣标签（10 个）与热门城市（10 个）初始数据（docs/08 的 T1.4）。
 * 后台「兴趣标签与城市」可增删改（docs/07 第 3.22 节）。
 */
class ClTagCitySeeder20261011 extends Seeder
{
    public const TAGS = ['旅行', '音乐', '电影', '美食', '健身', '读书', '摄影', '游戏', '咖啡', '宠物'];

    public const CITIES = ['北京', '上海', '广州', '深圳', '杭州', '成都', '重庆', '武汉', '西安', '南京'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tag::truncate();
        foreach (self::TAGS as $index => $name) {
            Tag::create(['name' => $name, 'sort' => $index + 1, 'enabled' => 1]);
        }

        City::truncate();
        foreach (self::CITIES as $index => $name) {
            City::create(['name' => $name, 'sort' => $index + 1, 'enabled' => 1]);
        }
    }
}
