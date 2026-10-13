<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 全员公告的已读记录（docs/04 第 6 节）。
 * 一对一的通知不需要这张表，它们把已读时间写在 `cl_notice.read_at` 上。
 *
 * @property int $id
 * @property int $notice_id
 * @property int $user_id
 */
class NoticeRead extends MineModel
{
    protected ?string $table = 'cl_notice_read';

    protected array $fillable = ['id', 'notice_id', 'user_id', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'notice_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
