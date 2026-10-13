package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** 一个礼物（docs/05 第 4.6 节 `/gift/list`）。价格由后台配，客户端只显示。 */
@Serializable
data class GiftDto(
    val id: Long = 0,
    val name: String = "",
    /** emoji 或图片地址（`docs/04` 两种都允许，现在种子用的是 emoji）。 */
    val icon: String = "",
    @SerialName("price_coins") val priceCoins: Int = 0,
)

/** 礼物列表。 */
@Serializable
data class GiftListDto(
    val list: List<GiftDto> = emptyList(),
)

/** 送礼的入参。`client_order_id` 必填：服务端靠它幂等，网络重试不会重复扣钱。 */
@Serializable
data class GiftSendRequestDto(
    @SerialName("receiver_id") val receiverId: Long,
    @SerialName("gift_id") val giftId: Long,
    @SerialName("client_order_id") val clientOrderId: String,
)

/** 送礼的返回：会话里那条礼物消息 + 最新余额。 */
@Serializable
data class GiftSendDataDto(
    val message: MessageDto = MessageDto(),
    val balance: Long = 0,
)
