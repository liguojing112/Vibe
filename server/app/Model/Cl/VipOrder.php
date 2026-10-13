<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 会员订单（docs/04 第 5 节）。
 *
 * `order_no` 就是客户端的 `client_order_id`（docs/05 `/vip/buy` 传的就是它）——
 * 和 `cl_gift_record.client_order_id` 一个用法：**同一个订单号只开一次会员**。
 * 后台客服开通那条路没有客户端订单号，由服务端自己生成一个。
 *
 * @property int $id
 * @property string $order_no
 * @property int $user_id
 * @property null|int $plan_id
 * @property int $days
 * @property int $price_coins
 * @property int $source
 * @property null|\Carbon\Carbon $expire_before
 * @property \Carbon\Carbon $expire_after
 * @property null|int $operator_id
 * @property null|string $remark
 */
class VipOrder extends MineModel
{
    /** 来源：App 里用金币买。 */
    public const SOURCE_COIN = 1;

    /** 来源：后台客服开通（金币 0，必须填备注）。 */
    public const SOURCE_ADMIN = 2;

    protected ?string $table = 'cl_vip_order';

    protected array $attributes = [
        'days' => 0,
        'price_coins' => 0,
        'source' => self::SOURCE_COIN,
    ];

    protected array $fillable = [
        'id', 'order_no', 'user_id', 'plan_id', 'days', 'price_coins', 'source',
        'expire_before', 'expire_after', 'operator_id', 'remark', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'plan_id' => 'integer',
        'days' => 'integer',
        'price_coins' => 'integer',
        'source' => 'integer',
        'expire_before' => 'datetime',
        'expire_after' => 'datetime',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
