<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 在线支付配置（后台「运营配置 → 在线支付」）。
 *
 * 商户 pid / key 由客户自己在后台填，不写进代码和 `.env`。
 * 默认关闭（`pay.enabled = 0`），配好 pid/key 再打开，App 才会出现「立即支付」。
 *
 * 同时要同步 `cl_config_seeder_20261011.php`：那份种子会 truncate 后重建配置，
 * 只改这里的话重新 seed 会把支付配置丢掉。
 */
return new class extends Migration {
    private const ROWS = [
        ['key' => 'pay.enabled', 'value' => '0', 'value_type' => 'bool', 'group' => 'pay', 'label' => '开启在线支付', 'remark' => '1 开 / 0 关；关闭时 App 只显示客服充值'],
        ['key' => 'pay.epay_url', 'value' => 'https://xpay.wsmfk.com', 'value_type' => 'string', 'group' => 'pay', 'label' => '码支付站点地址', 'remark' => '不含末尾斜杠，例如 https://xpay.wsmfk.com'],
        ['key' => 'pay.epay_pid', 'value' => '', 'value_type' => 'string', 'group' => 'pay', 'label' => '商户 ID（pid）', 'remark' => '码支付后台的商户 ID'],
        ['key' => 'pay.epay_key', 'value' => '', 'value_type' => 'string', 'group' => 'pay', 'label' => '商户密钥（key）', 'remark' => '只存服务端；后台留空表示不修改'],
        ['key' => 'pay.epay_type', 'value' => 'alipay', 'value_type' => 'string', 'group' => 'pay', 'label' => '支付方式', 'remark' => '固定 alipay（支付宝）'],
        ['key' => 'pay.notify_url', 'value' => 'https://clapi.cyjiaoyou.lol/api/v1/wallet/recharge/notify', 'value_type' => 'string', 'group' => 'pay', 'label' => '异步通知地址', 'remark' => '必须是公网能访问的本站地址'],
        ['key' => 'pay.return_url', 'value' => '', 'value_type' => 'string', 'group' => 'pay', 'label' => '同步跳转地址', 'remark' => 'App 支付不需要，可留空'],
        ['key' => 'pay.order_expire_minutes', 'value' => '30', 'value_type' => 'int', 'group' => 'pay', 'label' => '订单有效期（分钟）', 'remark' => '超过后待支付单自动关闭'],
    ];

    public function up(): void
    {
        foreach (self::ROWS as $row) {
            Db::table('cl_config')->updateOrInsert(
                ['key' => $row['key']],
                $row + ['updated_at' => date('Y-m-d H:i:s'), 'created_at' => date('Y-m-d H:i:s')],
            );
        }
    }

    public function down(): void
    {
        Db::table('cl_config')->whereIn('key', array_column(self::ROWS, 'key'))->delete();
    }
};
