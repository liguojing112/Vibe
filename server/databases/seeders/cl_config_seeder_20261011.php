<?php

declare(strict_types=1);

use App\Model\Cl\Config;
use Hyperf\Database\Seeders\Seeder;

/**
 * 运营配置默认值（docs/06 第 2 节全部 key + 协议文本）。
 * 后台「运营配置」可改；改 key 清单必须同步 docs/06 与本文件。
 */
class ClConfigSeeder20261011 extends Seeder
{
    public const DATA = [
        // ---- 2.1 汇率与基础 ----
        ['key' => 'coin.per_yuan', 'value' => '10', 'value_type' => 'int', 'group' => 'billing', 'label' => '金币汇率', 'remark' => '1 元 = N 金币，只用于充值展示和后台记录金额'],
        ['key' => 'point.per_yuan', 'value' => '100', 'value_type' => 'int', 'group' => 'billing', 'label' => '积分汇率', 'remark' => 'N 积分 = 1 元（提现换算）'],
        ['key' => 'chat.message_max_length', 'value' => '500', 'value_type' => 'int', 'group' => 'chat', 'label' => '单条文字最大长度', 'remark' => '私信文字长度上限'],
        ['key' => 'chat.image_max_mb', 'value' => '5', 'value_type' => 'int', 'group' => 'chat', 'label' => '图片最大 MB', 'remark' => '上传图片大小上限'],
        ['key' => 'chat.message_retention_months', 'value' => '6', 'value_type' => 'int', 'group' => 'chat', 'label' => '服务端消息保留月数', 'remark' => '超期普通消息由定时任务清理；敏感词命中与被举报的消息长期保留'],

        // ---- 2.2 私信计费 ----
        ['key' => 'chat.free_count_per_pair', 'value' => '3', 'value_type' => 'int', 'group' => 'billing', 'label' => '每对男女免费条数', 'remark' => '男用户前 N 条私信免费；0 = 第一条就收费'],
        ['key' => 'chat.mutual_follow_free', 'value' => '1', 'value_type' => 'bool', 'group' => 'billing', 'label' => '互相关注后私信免费', 'remark' => '1 开 / 0 关'],
        ['key' => 'chat.image_same_price', 'value' => '1', 'value_type' => 'bool', 'group' => 'billing', 'label' => '图片与文字同价', 'remark' => '本期固定 1'],

        // ---- 2.3 等级分成 ----
        ['key' => 'share.unverified_factor', 'value' => '5000', 'value_type' => 'int', 'group' => 'share', 'label' => '未实名分成系数', 'remark' => '万分比，5000 = 打五折'],
        ['key' => 'share.unverified_apply_gift', 'value' => '0', 'value_type' => 'bool', 'group' => 'share', 'label' => '未实名系数作用于礼物', 'remark' => '1 开 / 0 关（默认不作用）'],
        ['key' => 'share.gift_rate', 'value' => '3400', 'value_type' => 'int', 'group' => 'share', 'label' => '礼物分成比例', 'remark' => '万分比，3400 = 34%，不随等级变化'],

        // ---- 2.4 会员 ----
        ['key' => 'vip.daily_free_messages', 'value' => '10', 'value_type' => 'int', 'group' => 'vip', 'label' => '会员每日额外免费私信', 'remark' => '所有对象合计'],
        ['key' => 'vip.female_free_view', 'value' => '1', 'value_type' => 'bool', 'group' => 'vip', 'label' => '女用户免会员看完整访客', 'remark' => '1 开 / 0 关'],

        // ---- 2.5 提现 ----
        ['key' => 'withdraw.tiers', 'value' => '[50,100,200,500]', 'value_type' => 'json', 'group' => 'withdraw', 'label' => '提现档位（元）', 'remark' => 'JSON 数组'],
        ['key' => 'withdraw.daily_limit', 'value' => '1', 'value_type' => 'int', 'group' => 'withdraw', 'label' => '每人每天最多申请次数', 'remark' => '0 = 不限'],
        ['key' => 'withdraw.fee_rate', 'value' => '0', 'value_type' => 'int', 'group' => 'withdraw', 'label' => '提现手续费', 'remark' => '万分比'],
        ['key' => 'withdraw.need_real_name', 'value' => '1', 'value_type' => 'bool', 'group' => 'withdraw', 'label' => '提现必须实名', 'remark' => '1 开 / 0 关'],
        ['key' => 'withdraw.notice', 'value' => '提交后 1~3 个工作日内审核打款', 'value_type' => 'string', 'group' => 'withdraw', 'label' => '提现说明', 'remark' => 'App 提现页显示的说明文字'],

        // ---- 2.6 充值展示与客服 ----
        ['key' => 'recharge.packs', 'value' => '[{"coins":60,"yuan":6},{"coins":300,"yuan":30},{"coins":680,"yuan":68},{"coins":1280,"yuan":128},{"coins":2980,"yuan":298},{"coins":6480,"yuan":648}]', 'value_type' => 'json', 'group' => 'recharge', 'label' => '充值档位', 'remark' => 'JSON 数组，只做展示'],
        ['key' => 'service.wechat', 'value' => '', 'value_type' => 'string', 'group' => 'service', 'label' => '客服微信号', 'remark' => '后台填'],
        ['key' => 'service.qrcode_url', 'value' => '', 'value_type' => 'string', 'group' => 'service', 'label' => '客服二维码图片', 'remark' => '后台上传'],
        ['key' => 'service.phone', 'value' => '', 'value_type' => 'string', 'group' => 'service', 'label' => '客服电话', 'remark' => '后台填'],
        ['key' => 'compliance.disclosure', 'value' => '本平台为付费聊天服务，部分女用户为平台签约陪聊人员。', 'value_type' => 'string', 'group' => 'compliance', 'label' => '合规声明', 'remark' => '后台可改文字，但不能改成空（保存时校验）'],

        // ---- 在线支付（万世码支付 / 易支付 epay）----
        ['key' => 'pay.enabled', 'value' => '0', 'value_type' => 'bool', 'group' => 'pay', 'label' => '开启在线支付', 'remark' => '1 开 / 0 关；关闭时 App 只显示客服充值'],
        ['key' => 'pay.epay_url', 'value' => 'https://xpay.wsmfk.com', 'value_type' => 'string', 'group' => 'pay', 'label' => '码支付站点地址', 'remark' => '不含末尾斜杠'],
        ['key' => 'pay.epay_pid', 'value' => '', 'value_type' => 'string', 'group' => 'pay', 'label' => '商户 ID（pid）', 'remark' => '码支付后台的商户 ID'],
        ['key' => 'pay.epay_key', 'value' => '', 'value_type' => 'string', 'group' => 'pay', 'label' => '商户密钥（key）', 'remark' => '只存服务端；后台留空表示不修改'],
        ['key' => 'pay.epay_type', 'value' => 'alipay', 'value_type' => 'string', 'group' => 'pay', 'label' => '支付方式', 'remark' => '固定 alipay（支付宝）'],
        ['key' => 'pay.notify_url', 'value' => 'https://clapi.cyjiaoyou.lol/api/v1/wallet/recharge/notify', 'value_type' => 'string', 'group' => 'pay', 'label' => '异步通知地址', 'remark' => '必须是公网能访问的本站地址'],
        ['key' => 'pay.return_url', 'value' => '', 'value_type' => 'string', 'group' => 'pay', 'label' => '同步跳转地址', 'remark' => 'App 支付不需要，可留空'],
        ['key' => 'pay.order_expire_minutes', 'value' => '30', 'value_type' => 'int', 'group' => 'pay', 'label' => '订单有效期（分钟）', 'remark' => '超过后待支付单自动关闭'],

        // ---- AI 代聊的自定义模型 ----
        ['key' => 'ai.base_url', 'value' => 'https://api.deepseek.com', 'value_type' => 'string', 'group' => 'ai', 'label' => 'AI 服务地址', 'remark' => 'OpenAI 兼容接口的 Base URL，例如 https://api.deepseek.com'],
        ['key' => 'ai.model', 'value' => 'deepseek-chat', 'value_type' => 'string', 'group' => 'ai', 'label' => '模型名', 'remark' => '例如 deepseek-chat、gpt-4o-mini'],
        ['key' => 'ai.api_key', 'value' => '', 'value_type' => 'string', 'group' => 'ai', 'label' => 'API Key', 'remark' => '只存服务端；后台留空表示不修改'],

        // ---- 协议文本（docs/04 第 6 节要求一并种子）----
        ['key' => 'agreement.user', 'value' => "欢迎使用畅撩交友。\n\n本平台为付费聊天服务，部分女用户为平台签约陪聊人员。\n\n一、服务说明\n1. 男用户充值金币后，可用于发送私信、赠送礼物、开通会员。\n2. 女用户收到付费消息与礼物后按等级获得积分，积分可申请提现。\n3. 充值与提现均由平台客服人工处理，无线上支付通道。\n\n二、用户义务\n1. 请使用本人真实资料与照片，虚假资料将被封号。\n2. 不得发布色情低俗、广告诈骗、辱骂骚扰等内容。\n\n三、其他\n本协议可由平台根据运营需要调整，调整后在本页公示。", 'value_type' => 'string', 'group' => 'agreement', 'label' => '用户协议', 'remark' => '后台可编辑'],
        ['key' => 'agreement.privacy', 'value' => "畅撩交友重视你的隐私。\n\n1. 我们只收集服务必需的信息：手机号、昵称、头像、资料，以及女用户实名认证所需的姓名与身份证号。\n2. 身份证号加密存储，页面回显时打码。\n3. 未经你的同意，我们不会向第三方提供你的个人信息，法律法规另有规定的除外。\n4. 你可以随时在「设置」里注销账号。", 'value_type' => 'string', 'group' => 'agreement', 'label' => '隐私政策', 'remark' => '后台可编辑'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Config::truncate();
        foreach (self::DATA as $row) {
            Config::create($row);
        }
    }
}
