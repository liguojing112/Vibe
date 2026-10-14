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

    protected ?string $table = 'cl_recharge_order';

    protected array $fillable = [
        'id', 'order_no', 'user_id', 'coins', 'amount_fen', 'pay_channel',
        'remark', 'operator_id', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'coins' => 'integer',
        'amount_fen' => 'integer',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
