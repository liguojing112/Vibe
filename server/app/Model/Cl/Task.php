<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 任务配置（docs/04 第 5 节、docs/06 第 7 节）。男女各一行，用 `gender` 区分。
 *
 * `reward` 的单位跟着**领奖人的性别**走（男 = 金币、女 = 积分），所以这里不存单位：
 * 同一件事男女奖励不同，是两行配置，不是一行里的两个字段。
 *
 * @property int $id
 * @property string $name
 * @property string $description
 * @property string $icon
 * @property string $type
 * @property int $target
 * @property int $reward
 * @property int $gender
 * @property int $period
 * @property int $sort
 * @property int $enabled
 */
class Task extends MineModel
{
    /** 今天签到过就完成（每日）。 */
    public const TYPE_SIGN_IN = 'SIGN_IN';

    /** 相册审核通过的照片 ≥ 目标值（一次性）。 */
    public const TYPE_PROFILE_PHOTO = 'PROFILE_PHOTO';

    /** 自我介绍已通过审核（一次性）。 */
    public const TYPE_PROFILE_BIO = 'PROFILE_BIO';

    /** 今天给 ≥ 目标值个不同的人发过消息（每日）。 */
    public const TYPE_SEND_MESSAGE = 'SEND_MESSAGE';

    public const GENDER_ALL = 0;

    public const GENDER_MALE = 1;

    public const GENDER_FEMALE = 2;

    /** 周期：每日（`period_key` = yyyyMMdd）。 */
    public const PERIOD_DAILY = 1;

    /** 周期：一次性（`period_key` = ONCE）。 */
    public const PERIOD_ONCE = 2;

    protected ?string $table = 'cl_task';

    protected array $attributes = [
        'target' => 1,
        'reward' => 0,
        'gender' => self::GENDER_ALL,
        'period' => self::PERIOD_DAILY,
        'sort' => 0,
        'enabled' => 1,
    ];

    protected array $fillable = [
        'id', 'name', 'description', 'icon', 'type', 'target', 'reward',
        'gender', 'period', 'sort', 'enabled', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'target' => 'integer',
        'reward' => 'integer',
        'gender' => 'integer',
        'period' => 'integer',
        'sort' => 'integer',
        'enabled' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isEnabled(): bool
    {
        return $this->enabled === 1;
    }

    public function isDaily(): bool
    {
        return $this->period === self::PERIOD_DAILY;
    }

    /** 这个任务适不适合某个性别（`gender = 0` 是男女都适用）。 */
    public function appliesTo(int $gender): bool
    {
        return $this->gender === self::GENDER_ALL || $this->gender === $gender;
    }
}
