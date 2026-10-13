<?php

declare(strict_types=1);

use App\Model\Cl\Task;
use Hyperf\Database\Seeders\Seeder;

/**
 * 任务默认值（docs/06 第 7 节；名称和说明取自设计图 11）。
 *
 * **每种任务男女各一行**：奖励的单位不一样（男发金币、女发积分），
 * 用 `gender` 分开配比在代码里分支判断更清楚，后台也能各自改数量。
 */
class ClTaskSeeder20261013 extends Seeder
{
    public const DATA = [
        [
            'type' => Task::TYPE_SIGN_IN,
            'name' => '每日签到',
            'description' => '连续签到可领取玫瑰',
            'icon' => '📅',
            'target' => 1,
            'period' => Task::PERIOD_DAILY,
            'sort' => 1,
            'rewards' => [Task::GENDER_MALE => 5, Task::GENDER_FEMALE => 20],
        ],
        [
            'type' => Task::TYPE_PROFILE_PHOTO,
            'name' => '完善照片',
            'description' => '上传 3 张生活照，展示更多面的你',
            'icon' => '📷',
            'target' => 3,
            'period' => Task::PERIOD_ONCE,
            'sort' => 2,
            'rewards' => [Task::GENDER_MALE => 20, Task::GENDER_FEMALE => 50],
        ],
        [
            'type' => Task::TYPE_PROFILE_BIO,
            'name' => '写一句自我介绍',
            'description' => '让别人更快了解你',
            'icon' => '✍️',
            'target' => 1,
            'period' => Task::PERIOD_ONCE,
            'sort' => 3,
            'rewards' => [Task::GENDER_MALE => 10, Task::GENDER_FEMALE => 30],
        ],
        [
            'type' => Task::TYPE_SEND_MESSAGE,
            'name' => '和新朋友打招呼',
            'description' => '今天向 3 位新朋友发出问候',
            'icon' => '💬',
            'target' => 3,
            'period' => Task::PERIOD_DAILY,
            'sort' => 4,
            'rewards' => [Task::GENDER_MALE => 5, Task::GENDER_FEMALE => 20],
        ],
    ];

    public function run(): void
    {
        Task::truncate();
        foreach (self::DATA as $row) {
            foreach ($row['rewards'] as $gender => $reward) {
                Task::create([
                    'type' => $row['type'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'icon' => $row['icon'],
                    'target' => $row['target'],
                    'period' => $row['period'],
                    'sort' => $row['sort'],
                    'gender' => $gender,
                    'reward' => $reward,
                    'enabled' => 1,
                ]);
            }
        }
    }
}
