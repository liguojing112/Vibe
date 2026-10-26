package com.qichuang.changliao.core.data.model

import kotlinx.serialization.Serializable

/**
 * 金币余额与单价范围（P13）。价格全部来自服务端（等级配置现算），客户端不写死。
 *
 * 语音/视频价格在 `feature.call_enabled` 关着时服务端**不返回**（[callEnabled] 为 false），
 * 页面据此决定要不要显示那两行。
 */
data class WalletCoin(
    val balance: Long = 0,
    val messagePriceMin: Int = 0,
    val messagePriceMax: Int = 0,
    val callEnabled: Boolean = false,
    val voicePriceMin: Int? = null,
    val voicePriceMax: Int? = null,
    val videoPriceMin: Int? = null,
    val videoPriceMax: Int? = null,
)

/**
 * 一条金币流水（P24）。[change] 正数是进账、负数是消费。
 *
 * [type] 是服务端 `cl_coin_log.type` 的原值（RECHARGE/MESSAGE/GIFT/VIP/TASK/ADMIN_DEDUCT），
 * 页面用它挑图标；文案用服务端拼好的 [title]（「私信 · 小满」这种），客户端不拼。
 */
data class CoinLogEntry(
    val id: Long = 0,
    val type: String = "",
    val title: String = "",
    val change: Long = 0,
    val balanceAfter: Long = 0,
    /** 毫秒；解析不了就是 0 */
    val createdAt: Long = 0,
) {
    val isIncome: Boolean get() = change > 0
}

/** 收支明细一页。 */
data class CoinLogPage(
    val list: List<CoinLogEntry> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val hasMore: Boolean = false,
)

/** 收支明细的三个 Tab（P24）。 */
enum class CoinLogTab(val label: String, val apiValue: String) {
    ALL("全部", "all"),
    RECHARGE("充值", "recharge"),
    SPEND("消费", "spend"),
}

/**
 * 余额不足弹窗（P30）要的数据：`20001` 的 `data` 就是 `{"balance": 4, "needed": 5}`（服务端抛的）。
 *
 * 发私信（P06）和送礼物（P23/P31）都会碰到「余额不足」，所以放在这里共用。
 */
@Serializable
data class CoinNotEnough(
    val balance: Long = 0,
    val needed: Long = 0,
) {
    /** 还差多少（服务端给的是「需要多少」和「现在有多少」）。 */
    val short: Long = (needed - balance).coerceAtLeast(0)
}

/** 一个充值档位（`recharge.packs`）。[index] 是下单时传给服务端的下标，金额以服务端为准。 */
data class RechargePack(
    val coins: Int = 0,
    val yuan: Int = 0,
    val index: Int = 0,
) {
    /** 网格组件要一个 id，用金币数就够了（档位不会重复）。 */
    val id: Long get() = coins.toLong()
}

/** 客服信息（后台配置：微信号 / 二维码 / 电话）。 */
data class RechargeService(
    val wechat: String = "",
    val qrcodeUrl: String = "",
    val phone: String = "",
) {
    val hasAny: Boolean get() = wechat.isNotBlank() || qrcodeUrl.isNotBlank() || phone.isNotBlank()
}

/** 充值页要的一切（P22）。 */
data class RechargeInfo(
    val balance: Long = 0,
    val packs: List<RechargePack> = emptyList(),
    /** 在线支付是否可用；false 时页面只显示客服充值。 */
    val payEnabled: Boolean = false,
    val service: RechargeService = RechargeService(),
    /** 合规声明（铁律 13，服务端保证非空）。 */
    val complianceDisclosure: String = "",
    val notice: String = "",
)

/** 在线充值单（下单 / 轮询共用）。 */
data class RechargeOrder(
    val orderNo: String = "",
    val coins: Int = 0,
    val amountFen: Long = 0,
    val amountText: String = "",
    /** 支付宝付款链接：本地生成二维码，也可以直接交给支付宝 App。 */
    val payUrl: String = "",
    val status: Int = STATUS_PENDING,
    val expireAt: String = "",
    val balance: Long = 0,
) {
    companion object {
        const val STATUS_PENDING = 0
        const val STATUS_PAID = 1
        const val STATUS_CLOSED = 2
    }

    val isPaid: Boolean get() = status == STATUS_PAID
    val isClosed: Boolean get() = status == STATUS_CLOSED
}
