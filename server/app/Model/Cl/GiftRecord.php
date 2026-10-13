<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 赠送记录（docs/04 第 5 节）。存**送出那一刻**的礼物名与价格，后台以后调价也不影响历史对账。
 *
 * @property int $id
 * @property string $client_order_id
 * @property int $sender_id
 * @property int $receiver_id
 * @property int $gift_id
 * @property string $gift_name
 * @property int $price_coins
 * @property int $share_rate
 * @property int $receiver_points
 * @property null|int $message_id
 */
class GiftRecord extends MineModel
{
    protected ?string $table = 'cl_gift_record';

    protected array $attributes = [
        'share_rate' => 0,
        'receiver_points' => 0,
    ];

    protected array $fillable = [
        'id', 'client_order_id', 'sender_id', 'receiver_id', 'gift_id', 'gift_name',
        'price_coins', 'share_rate', 'receiver_points', 'message_id', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'gift_id' => 'integer',
        'price_coins' => 'integer',
        'share_rate' => 'integer',
        'receiver_points' => 'integer',
        'message_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
