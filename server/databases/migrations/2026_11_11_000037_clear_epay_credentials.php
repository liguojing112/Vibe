<?php

declare(strict_types=1);

use App\Service\Cl\Config\AppConfigService;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Migrations\Migration;
use Hyperf\DbConnection\Db;

/**
 * 清空在线支付的商户凭据（客户 2026-11-11）。
 *
 * 现在填的是经理自己的 pid / key，不是客户商户号的。先撤掉，
 * 等客户拿到自己的商户凭据后到后台「运营配置 → 在线支付」自己填。
 *
 * `EpayService::enabled()` 要求开关打开**且** pid / key / 站点地址都不为空，
 * 所以清空这两项之后：App 端不会再出现在线支付入口，不会有人拿别人的商户号付款。
 * 开关本身保持打开，客户填完就自动生效，不用再改开关。
 *
 * 配置有 Redis 缓存（`cl:config`），只改库不清缓存的话线上还会读到旧值 —— 这里一起清掉。
 */
return new class extends Migration {
    /** 只清这两个：商户号 + 密钥。站点地址、异步通知地址不是凭据，留着方便客户核对。 */
    private const KEYS = ['pay.epay_pid', 'pay.epay_key'];

    public function up(): void
    {
        Db::table('cl_config')->whereIn('key', self::KEYS)->update(['value' => '']);

        ApplicationContext::getContainer()->get(AppConfigService::class)->forget();
    }

    public function down(): void
    {
        // 不回滚：撤掉的正是别人的商户凭据，没有写回去的道理。
    }
};
