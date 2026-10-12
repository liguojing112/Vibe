<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 会话（docs/04 第 3 节）：**一对用户一条**，按 `user_low_id < user_high_id` 存，
 * 这样同一对人不管谁先发起都命中同一条。
 *
 * @property int $id
 * @property int $user_low_id
 * @property int $user_high_id
 * @property int $male_id
 * @property int $female_id
 * @property int $male_sent_count
 * @property null|int $last_message_id
 * @property null|\Carbon\Carbon $last_message_at
 * @property int $low_unread
 * @property int $high_unread
 * @property null|\Carbon\Carbon $low_deleted_at
 * @property null|\Carbon\Carbon $high_deleted_at
 */
class Conversation extends MineModel
{
    protected ?string $table = 'cl_conversation';

    /**
     * 带上表里的默认值：`create()` 之后模型对象才会有这些值
     * （DB 的默认值是 DB 的事，不会回填到内存对象 —— 这里踩过坑：`level` 读出来是 null）。
     */
    protected array $attributes = [
        'male_sent_count' => 0,
        'low_unread' => 0,
        'high_unread' => 0,
    ];

    protected array $fillable = [
        'id', 'user_low_id', 'user_high_id', 'male_id', 'female_id', 'male_sent_count',
        'last_message_id', 'last_message_at', 'low_unread', 'high_unread',
        'low_deleted_at', 'high_deleted_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_low_id' => 'integer',
        'user_high_id' => 'integer',
        'male_id' => 'integer',
        'female_id' => 'integer',
        'male_sent_count' => 'integer',
        'last_message_id' => 'integer',
        'last_message_at' => 'datetime',
        'low_unread' => 'integer',
        'high_unread' => 'integer',
        'low_deleted_at' => 'datetime',
        'high_deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 把一对用户 id 排成 (`low`, `high`)。
     *
     * @return array{0: int, 1: int}
     */
    public static function pair(int $a, int $b): array
    {
        return $a <= $b ? [$a, $b] : [$b, $a];
    }

    /** 我在这个会话里对应哪个「未读」列。 */
    public function unreadColumnFor(int $userId): string
    {
        return $userId === $this->user_low_id ? 'low_unread' : 'high_unread';
    }

    public function unreadFor(int $userId): int
    {
        return (int) $this->{$this->unreadColumnFor($userId)};
    }

    /** 我在这个会话里对应哪个「删除时间」列（删了之后来新消息会话要重新出现）。 */
    public function deletedAtColumnFor(int $userId): string
    {
        return $userId === $this->user_low_id ? 'low_deleted_at' : 'high_deleted_at';
    }
}
