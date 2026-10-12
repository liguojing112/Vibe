<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 用户相册（docs/04 第 1 节）。
 * 审核通过前，别人看到的还是旧相册。
 */
class UserPhoto extends MineModel
{
    public const AUDIT_PENDING = 0;

    public const AUDIT_APPROVED = 1;

    public const AUDIT_REJECTED = 2;

    protected ?string $table = 'cl_user_photo';

    protected array $fillable = [
        'id', 'user_id', 'url', 'sort', 'audit_status', 'reject_reason', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'sort' => 'integer',
        'audit_status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
