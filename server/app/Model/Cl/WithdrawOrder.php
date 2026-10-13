<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 提现单（docs/04 第 4 节）。
 *
 * 状态只能按 待审核 → 通过 → 已打款、或 待审核/通过 → 驳回 这两条路走
 * （docs/06 第 9.2 节的表），别的流转一律拒绝。
 *
 * 金额字段全是**分**：手续费要「按分向下取整」，用分算才不会出现浮点误差。
 *
 * @property int $id
 * @property string $order_no
 * @property int $user_id
 * @property int $amount_fen
 * @property int $points
 * @property int $fee_fen
 * @property int $actual_fen
 * @property string $account_type
 * @property string $account_no
 * @property string $account_name
 * @property null|string $bank_name
 * @property int $status
 * @property null|string $reject_reason
 * @property null|string $pay_proof
 * @property null|int $reviewer_id
 * @property null|int $payer_id
 * @property null|\Carbon\Carbon $reviewed_at
 * @property null|\Carbon\Carbon $paid_at
 */
class WithdrawOrder extends MineModel
{
    /** 待审核。 */
    public const STATUS_PENDING = 0;

    /** 财务审核通过，等打款（积分仍冻结）。 */
    public const STATUS_APPROVED = 1;

    /** 已驳回（积分原路退回）。 */
    public const STATUS_REJECTED = 2;

    /** 已打款（冻结真正扣掉）。 */
    public const STATUS_PAID = 3;

    public const STATUS_TEXTS = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_APPROVED => '审核通过，待打款',
        self::STATUS_REJECTED => '已驳回',
        self::STATUS_PAID => '已打款',
    ];

    protected ?string $table = 'cl_withdraw_order';

    protected array $attributes = [
        'fee_fen' => 0,
        'status' => self::STATUS_PENDING,
    ];

    protected array $fillable = [
        'id', 'order_no', 'user_id', 'amount_fen', 'points', 'fee_fen', 'actual_fen',
        'account_type', 'account_no', 'account_name', 'bank_name', 'status', 'reject_reason',
        'pay_proof', 'reviewer_id', 'payer_id', 'reviewed_at', 'paid_at', 'created_at', 'updated_at',
    ];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'amount_fen' => 'integer',
        'points' => 'integer',
        'fee_fen' => 'integer',
        'actual_fen' => 'integer',
        'status' => 'integer',
        'reviewer_id' => 'integer',
        'payer_id' => 'integer',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
