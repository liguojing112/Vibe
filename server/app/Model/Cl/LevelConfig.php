<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 女用户等级配置表（docs/04 第 6 节）。
 *
 * @property int $id
 * @property int $level
 * @property string $name
 * @property int $threshold_points
 * @property int $message_price
 * @property int $message_share_rate
 * @property int $voice_price
 * @property int $video_price
 */
final class LevelConfig extends MineModel
{
    protected ?string $table = 'cl_level_config';

    protected array $fillable = ['id', 'level', 'name', 'threshold_points', 'message_price', 'message_share_rate', 'voice_price', 'video_price', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'level' => 'integer',
        'threshold_points' => 'integer',
        'message_price' => 'integer',
        'message_share_rate' => 'integer',
        'voice_price' => 'integer',
        'video_price' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
