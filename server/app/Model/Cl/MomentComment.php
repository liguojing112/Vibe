<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 朋友圈评论。
 *
 * @property int $id
 * @property int $moment_id
 * @property int $user_id
 * @property string $content
 * @property int $status
 * @property null|\Carbon\Carbon $deleted_at
 * @property \Carbon\Carbon $created_at
 */
class MomentComment extends MineModel
{
    public const STATUS_NORMAL = 1;

    public const STATUS_HIDDEN = 2;

    public const MAX_CONTENT = 500;

    protected ?string $table = 'cl_moment_comment';

    protected array $fillable = [
        'id', 'moment_id', 'user_id', 'content', 'status', 'deleted_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'moment_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
