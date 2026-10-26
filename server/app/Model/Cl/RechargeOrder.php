<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Carbon\Carbon;
use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 后台充值单（docs/04 第 4 节）。
 *
 * @property int $id
 * @property string $order_no
 * @property int $user_id
 * @property int $coins
 * @property int $amount_fen
 * @property string $pay_channel
 * @property string $source
 * @property int $status
 * @property null|string $trade_no
 * @property null|string $pay_url
 * @property null|Carbon $paid_at
 * @property null|string $remark
 * @property null|int $operator_id
 * @property null|Carbon $created_at
 * @property null|Carbon $updated_at
 */
final class RechargeOrder extends MineModel
{
    public const CHANNEL_WECHAT = 'WECHAT';

    public const CHANNEL_ALIPAY = 'ALIPAY';

    public const CHANNEL_OTHER = 'OTHER';

    /** 赠送（实收 0）。 */
    public const CHANNEL_GIFT = 'GIFT';

    public const CHANNELS = [
        self::CHANNEL_WECHAT,
        self::CHANNEL_ALIPAY,
        self::CHANNEL_OTHER,
        self::CHANNEL_GIFT,
    ];

    /** 人工后台充值。 */
    public const SOURCE_ADMIN = 'ADMIN';

    /** App 在线支付。 */
    public const SOURCE_ONLINE = 'ONLINE';

    public const STATUS_PENDING = 0;

    public const STATUS_PAID = 1;

    public const STATUS_CLOSED = 2;

    protected ?string $table = 'cl_recharge_order';

    protected array $fillable = [
        'id', 'order_no', 'user_id', 'coins', 'amount_fen', 'pay_channel',
        'source', 'status', 'trade_no', 'pay_url', 'paid_at',
        'remark', 'operator_id', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'coins' => 'integer',
        'amount_fen' => 'integer',
        'status' => 'integer',
        'operator_id' => 'integer',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
