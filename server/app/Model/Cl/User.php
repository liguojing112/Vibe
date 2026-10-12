<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * App 用户表（docs/04 第 1 节）。
 * 性别 1 男 2 女；状态 1 正常 2 封禁 3 已注销。
 *
 * @property int $id
 * @property string $account
 * @property null|string $phone
 * @property string $password
 * @property int $gender
 * @property string $nickname
 * @property string $avatar
 * @property string $bio
 * @property int $level
 * @property int $total_earned_points
 * @property int $status
 * @property null|string $ban_reason
 * @property null|\Carbon\Carbon $ban_until
 * @property int $login_fail_count
 * @property null|\Carbon\Carbon $locked_until
 */
final class User extends MineModel
{
    public const GENDER_MALE = 1;

    public const GENDER_FEMALE = 2;

    public const STATUS_NORMAL = 1;

    public const STATUS_BANNED = 2;

    public const STATUS_CANCELLED = 3;

    /** 实名状态（docs/04 第 1 节 `real_name_status`）。 */
    public const REAL_NAME_NONE = 0;

    public const REAL_NAME_AUDITING = 1;

    public const REAL_NAME_PASSED = 2;

    public const REAL_NAME_REJECTED = 3;

    protected ?string $table = 'cl_user';

    protected array $fillable = [
        'id', 'account', 'phone', 'password', 'gender', 'nickname', 'avatar', 'bio',
        'birthday', 'city', 'height', 'job', 'education', 'dating_purpose', 'tags',
        'level', 'level_locked', 'total_earned_points', 'vip_expire_at', 'real_name_status',
        'status', 'ban_until', 'ban_reason', 'source', 'batch_no',
        'show_in_nearby', 'show_in_recommend', 'last_active_at',
        'login_fail_count', 'locked_until', 'last_login_ip', 'last_login_device',
        'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'gender' => 'integer',
        'level' => 'integer',
        'level_locked' => 'integer',
        'total_earned_points' => 'integer',
        'real_name_status' => 'integer',
        'status' => 'integer',
        'source' => 'integer',
        'show_in_nearby' => 'integer',
        'show_in_recommend' => 'integer',
        'login_fail_count' => 'integer',
        'tags' => 'array',
        'birthday' => 'date',
        'vip_expire_at' => 'datetime',
        'ban_until' => 'datetime',
        'locked_until' => 'datetime',
        'last_active_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isMale(): bool
    {
        return $this->gender === self::GENDER_MALE;
    }

    public function isFemale(): bool
    {
        return $this->gender === self::GENDER_FEMALE;
    }

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    /** 会员是否还在有效期内（docs/04 第 1 节 `vip_expire_at`）。 */
    public function isVip(): bool
    {
        $expireAt = $this->vip_expire_at;

        return $expireAt !== null && $expireAt->isFuture();
    }

    /**
     * 年龄（由 `birthday` 算，没填返回 null）。
     *
     * 注意 `birthday` cast 出来的是 `\Carbon\Carbon`，不是 Hyperf 的 Carbon 子类，
     * 所以不判断类型、直接 parse 一份出来算；两边都取当天零点，避免时区差一天。
     */
    public function age(): ?int
    {
        $birthday = $this->birthday;
        if ($birthday === null) {
            return null;
        }

        return (int) Carbon::parse($birthday->format('Y-m-d'))
            ->startOfDay()
            ->diffInYears(Carbon::now()->startOfDay());
    }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->password);
    }
}
