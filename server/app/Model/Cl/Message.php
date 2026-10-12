<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 消息（docs/04 第 3 节）。
 *
 * **定位**：服务端只做**转发、计费与监管留存**，不是 App 聊天记录的展示数据源
 * （展示以安卓本地库为准，见 `10` 文档）。所以这里的字段都围着「计费可对账 + 监管可取证」转。
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $sender_id
 * @property int $receiver_id
 * @property string $client_msg_id
 * @property int $type
 * @property string $content
 * @property int $price_coins
 * @property null|string $free_reason
 * @property null|int $receiver_level
 * @property null|int $share_rate
 * @property int $receiver_points
 * @property int $sensitive_hit
 * @property int $review_status
 * @property null|\Carbon\Carbon $read_at
 */
class Message extends MineModel
{
    public const TYPE_TEXT = 1;

    public const TYPE_IMAGE = 2;

    public const TYPE_GIFT = 3;

    public const TYPE_SYSTEM = 4;

    public const REVIEW_NONE = 0;

    public const REVIEW_PENDING = 1;

    public const REVIEW_NORMAL = 2;

    public const REVIEW_VIOLATION = 3;

    protected ?string $table = 'cl_message';

    /** 表里的默认值也写在模型上（`create()` 后内存对象就带着它们，别等 DB 回填）。 */
    protected array $attributes = [
        'price_coins' => 0,
        'receiver_points' => 0,
        'sensitive_hit' => 0,
        'review_status' => self::REVIEW_NONE,
    ];

    protected array $fillable = [
        'id', 'conversation_id', 'sender_id', 'receiver_id', 'client_msg_id', 'type', 'content',
        'price_coins', 'free_reason', 'receiver_level', 'share_rate', 'receiver_points',
        'sensitive_hit', 'review_status', 'read_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'conversation_id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'type' => 'integer',
        'price_coins' => 'integer',
        'receiver_level' => 'integer',
        'share_rate' => 'integer',
        'receiver_points' => 'integer',
        'sensitive_hit' => 'integer',
        'review_status' => 'integer',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** 只有文字和图片算「普通消息」，礼物不计入 `male_sent_count`（`06` 第 10 节场景 16）。 */
    public function isNormalMessage(): bool
    {
        return $this->type === self::TYPE_TEXT || $this->type === self::TYPE_IMAGE;
    }
}
