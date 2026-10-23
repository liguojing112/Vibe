<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 朋友圈动态。
 *
 * `images` 是 URL 数组（最多 9 张），`like_count` / `comment_count` 是冗余计数，
 * 只在事务里跟着点赞/评论一起改，查询时不走 COUNT。
 *
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property null|array<int,string> $images
 * @property int $like_count
 * @property int $comment_count
 * @property int $status
 * @property null|\Carbon\Carbon $deleted_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Moment extends MineModel
{
    public const STATUS_NORMAL = 1;

    public const STATUS_HIDDEN = 2;

    public const MAX_IMAGES = 9;

    public const MAX_CONTENT = 500;

    protected ?string $table = 'cl_moment';

    protected array $fillable = [
        'id', 'user_id', 'content', 'images', 'like_count', 'comment_count',
        'status', 'deleted_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'images' => 'array',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'status' => 'integer',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
