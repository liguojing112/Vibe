package com.qichuang.changliao.feature.call

import com.qichuang.changliao.core.data.model.CallKind

/**
 * 拨打前价格确认（docs/01 P17，设计图 17）。
 *
 * **本期只做页面不做功能**（`01` 第 4 节）：这里没有任何通话状态，也没有任何扣费 ——
 * 点「确认拨打」只弹一句「功能暂未开放」。真正要接音视频服务时，从这里往后才是真流程。
 */
data class CallUiState(
    val kind: CallKind = CallKind.VIDEO,
    val peerId: Long = 0,
    val peerName: String = "",
    val peerAvatar: String? = null,
    val peerOnline: Boolean = false,
    val peerVerified: Boolean = false,
    /** 每分钟单价（金币）。服务端按对方等级算好给的（铁律 6），客户端不做任何换算。 */
    val price: Long = 0,
    /** 我的余额（金币）。 */
    val balance: Long = 0,
    val loading: Boolean = true,
    val error: String? = null,
    val toast: String? = null,
) {
    /** 「1 对 1 语音通话」/「1 对 1 视频通话」。 */
    val callTypeText: String get() = "1 对 1 " + kind.callLabel

    /** 「120 金币 / 分钟」。 */
    val priceText: String get() = "$price 金币 / 分钟"

    /** 「360 金币」。 */
    val balanceText: String get() = "$balance 金币"

    /**
     * 「约 3 分钟」——按当前余额能聊多久。
     *
     * 0 单价（服务端没配）时给「—」，别显示「约 0 分钟」或者直接崩。
     */
    val maxMinutesText: String
        get() = if (price <= 0) "—" else "约 ${balance / price} 分钟"

    /** 底部按钮上的「120 币/分钟」；没价格就只写「确认拨打」。 */
    val confirmSubText: String
        get() = if (price <= 0) "" else "$price 币/分钟"
}
