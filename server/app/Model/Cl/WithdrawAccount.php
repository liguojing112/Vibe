<?php

declare(strict_types=1);

namespace App\Model\Cl;

use Hyperf\DbConnection\Model\Model as MineModel;

/**
 * 女用户保存的收款信息（docs/04 第 4 节）。一个人一份，下次提现默认带出来（`docs/01` P28）。
 *
 * 注意这里**没有收款人姓名**：收款人一律取实名姓名（提现单上的 `account_name`），
 * 免得用户把钱提到别人的账上。
 *
 * @property int $id
 * @property int $user_id
 * @property string $account_type
 * @property string $account_no
 * @property null|string $bank_name
 */
class WithdrawAccount extends MineModel
{
    public const TYPE_ALIPAY = 'ALIPAY';

    public const TYPE_WECHAT = 'WECHAT';

    public const TYPE_BANK = 'BANK';

    protected ?string $table = 'cl_withdraw_account';

    protected array $fillable = ['id', 'user_id', 'account_type', 'account_no', 'bank_name', 'created_at', 'updated_at'];

    protected array $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
