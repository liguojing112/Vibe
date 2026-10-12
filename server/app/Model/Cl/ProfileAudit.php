<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 资料修改审核（docs/04 第 1 节）。
 * 同一用户同一字段只保留一条待审记录（新提交覆盖旧的待审记录）。
 */
class ProfileAudit extends MineModel
{
    public const FIELD_AVATAR = 'avatar';

    public const FIELD_NICKNAME = 'nickname';

    public const FIELD_BIO = 'bio';

    public const FIELD_PHOTO = 'photo';

    public const STATUS_PENDING = 0;

    public const STATUS_APPROVED = 1;

    public const STATUS_REJECTED = 2;

    /** 需要走审核的字段（其余字段直接生效）。 */
    public const AUDIT_FIELDS = [
        self::FIELD_AVATAR,
        self::FIELD_NICKNAME,
        self::FIELD_BIO,
    ];

    protected ?string $table = 'cl_profile_audit';

    protected array $fillable = [
        'id', 'user_id', 'field', 'old_value', 'new_value', 'status',
        'reject_reason', 'auditor_id', 'audited_at', 'created_at', 'updated_at',
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
