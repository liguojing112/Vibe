<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 实名认证（docs/04 第 1 节）。一个人一条，重新提交是**覆盖**不是新增。
 *
 * 状态值和 `cl_user.real_name_status` 是同一套（`User::REAL_NAME_*`），
 * 区别只是这张表没有 0（没有「没提交过」这种行）。
 *
 * @property int $id
 * @property int $user_id
 * @property string $real_name
 * @property string $id_card_encrypted
 * @property string $id_card_mask
 * @property string $id_card_hash
 * @property int $status
 * @property null|string $reject_reason
 * @property null|int $auditor_id
 * @property null|\Carbon\Carbon $audited_at
 */
class RealName extends MineModel
{
    public const STATUS_AUDITING = 1;

    public const STATUS_PASSED = 2;

    public const STATUS_REJECTED = 3;

    protected ?string $table = 'cl_real_name';

    protected array $attributes = [
        'status' => self::STATUS_AUDITING,
    ];

    protected array $fillable = [
        'id', 'user_id', 'real_name', 'id_card_encrypted', 'id_card_mask', 'id_card_hash',
        'status', 'reject_reason', 'auditor_id', 'audited_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'auditor_id' => 'integer',
        'audited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
