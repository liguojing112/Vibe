<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 系统通知 / 互动通知（docs/04 第 6 节）。
 *
 * `user_id = 0` 是全员公告（已读状态存 `cl_notice_read`），其余是一对一的通知
 * （已读时间直接写在这一行的 `read_at` 上）。
 *
 * @property int $id
 * @property int $user_id
 * @property int $category
 * @property string $title
 * @property string $content
 * @property null|string $biz_type
 * @property null|int $biz_id
 * @property null|\Carbon\Carbon $read_at
 */
class Notice extends MineModel
{
    /** 分类：系统通知（P10 的第一个 Tab）。 */
    public const CATEGORY_SYSTEM = 1;

    /** 分类：互动（关注、收到礼物这类）。 */
    public const CATEGORY_INTERACT = 2;

    /** 全员公告的收件人 id。 */
    public const ALL_USERS = 0;

    /** 业务类型（客户端据此挑图标、也方便以后跳转）。 */
    public const BIZ_LEVEL_UP = 'LEVEL_UP';

    public const BIZ_ANNOUNCE = 'ANNOUNCE';

    protected ?string $table = 'cl_notice';

    protected array $attributes = [
        'content' => '',
    ];

    protected array $fillable = [
        'id', 'user_id', 'category', 'title', 'content', 'biz_type', 'biz_id', 'read_at',
        'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'category' => 'integer',
        'biz_id' => 'integer',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
