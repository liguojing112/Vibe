package com.qichuang.changliao.core.data.model

/**
 * 一个礼物（P23/P31 的网格项）。价格来自后台（铁律 6：客户端只显示）。
 */
data class Gift(
    val id: Long = 0,
    val name: String = "",
    /** emoji 或图片地址。 */
    val icon: String = "",
    val priceCoins: Int = 0,
)

/** 送礼的结果：会话里那条礼物消息 + 最新余额。 */
data class GiftSendResult(
    val message: ChatMessage,
    val balance: Long = 0,
)
