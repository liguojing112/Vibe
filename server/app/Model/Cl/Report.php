<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 举报（docs/04 第 3 节）。
 *
 * 现在只有「消息清理要跳过被举报的消息」这一处用它（T4.9）；
 * 提交举报、后台处理是 P6 的 T6.4 / T7.5。
 *
 * @property int $id
 * @property int $reporter_id
 * @property int $target_id
 * @property string $reason
 * @property string $content
 * @property null|array $images
 * @property null|int $message_id
 * @property int $status
 */
class Report extends MineModel
{
    /** 举报原因（docs/01 P05 的弹窗）。 */
    public const REASON_PORN = 'PORN';

    public const REASON_FRAUD = 'FRAUD';

    public const REASON_ABUSE = 'ABUSE';

    public const REASON_FAKE = 'FAKE';

    public const REASON_OTHER = 'OTHER';

    public const STATUS_PENDING = 0;

    public const STATUS_VIOLATION = 1;

    public const STATUS_INNOCENT = 2;

    protected ?string $table = 'cl_report';

    protected array $attributes = [
        'content' => '',
        'status' => self::STATUS_PENDING,
    ];

    protected array $fillable = [
        'id', 'reporter_id', 'target_id', 'reason', 'content', 'images', 'message_id',
        'status', 'handle_result', 'handler_id', 'handled_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'reporter_id' => 'integer',
        'target_id' => 'integer',
        'images' => 'array',
        'message_id' => 'integer',
        'status' => 'integer',
        'handler_id' => 'integer',
        'handled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
