package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** 一个会员套餐（docs/05 第 4.9 节 `/vip/info`）。价格由后台配，客户端只显示。 */
@Serializable
data class VipPlanDto(
    val id: Long = 0,
    val name: String = "",
    val days: Int = 0,
    @SerialName("price_coins") val priceCoins: Int = 0,
    /** 折合人民币（整元）—— 设计图 12 的横幅写的是「¥39 / 月」。 */
    @SerialName("price_yuan") val priceYuan: Int = 0,
)

/** 一条会员权益（文案和图标都是服务端给的）。 */
@Serializable
data class VipBenefitDto(
    val icon: String = "",
    val title: String = "",
    val desc: String = "",
)

/** 会员中心（P12）：套餐、权益、我的到期时间。 */
@Serializable
data class VipInfoDto(
    @SerialName("is_vip") val isVip: Boolean = false,
    /** 有效会员才有（过期时服务端返回 null，不给一个过去的日期）。 */
    @SerialName("expire_at") val expireAt: String? = null,
    val plans: List<VipPlanDto> = emptyList(),
    val benefits: List<VipBenefitDto> = emptyList(),
    val balance: Long = 0,
    /** 「会员与聊天费用」提示卡（服务端给文案，客户端只管显示）。 */
    val notice: String = "",
)

/** 买套餐的入参。`client_order_id` 必填：服务端靠它幂等，网络重试不会重复扣钱。 */
@Serializable
data class VipBuyRequestDto(
    @SerialName("plan_id") val planId: Long,
    @SerialName("client_order_id") val clientOrderId: String,
)

/** 买套餐的返回：新的到期时间 + 最新余额。 */
@Serializable
data class VipBuyDataDto(
    @SerialName("expire_at") val expireAt: String? = null,
    val days: Int = 0,
    @SerialName("price_coins") val priceCoins: Int = 0,
    val balance: Long = 0,
)
