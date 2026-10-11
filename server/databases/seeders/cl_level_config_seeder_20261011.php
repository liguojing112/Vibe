<?php

declare(strict_types=1);

use App\Model\Cl\LevelConfig;
use Hyperf\Database\Seeders\Seeder;

/**
 * 女用户等级配置默认值（docs/06 第 2.3 节）。
 */
class ClLevelConfigSeeder20261011 extends Seeder
{
    public const DATA = [
        ['level' => 1, 'name' => 'Lv1', 'threshold_points' => 0, 'message_price' => 5, 'message_share_rate' => 3000, 'voice_price' => 60, 'video_price' => 120],
        ['level' => 2, 'name' => 'Lv2', 'threshold_points' => 20000, 'message_price' => 8, 'message_share_rate' => 3500, 'voice_price' => 80, 'video_price' => 160],
        ['level' => 3, 'name' => 'Lv3', 'threshold_points' => 100000, 'message_price' => 10, 'message_share_rate' => 4000, 'voice_price' => 100, 'video_price' => 200],
        ['level' => 4, 'name' => 'Lv4', 'threshold_points' => 300000, 'message_price' => 15, 'message_share_rate' => 4500, 'voice_price' => 150, 'video_price' => 300],
        ['level' => 5, 'name' => 'Lv5', 'threshold_points' => 1000000, 'message_price' => 20, 'message_share_rate' => 5000, 'voice_price' => 200, 'video_price' => 400],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LevelConfig::truncate();
        foreach (self::DATA as $row) {
            LevelConfig::create($row);
        }
    }
}
