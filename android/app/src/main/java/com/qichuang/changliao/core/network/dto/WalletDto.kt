package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 金币余额与单价范围（docs/05 第 4.7 节 `/wallet/coin`，P13）。
 *
 * 语音/视频价格**开关关着时服务端不返回**，所以这里都是可空的 ——
 * 页面据此决定要不要显示那两行。
 */
@Serializable
data class WalletCoinDto(
    val balance: Long = 0,
    @SerialName("message_price_min") val messagePriceMin: Int = 0,
    @SerialName("message_price_max") val messagePriceMax: Int = 0,
    @SerialName("call_enabled") val callEnabled: Boolean = false,
    @SerialName("voice_price_min") val voicePriceMin: Int? = null,
    @SerialName("voice_price_max") val voicePriceMax: Int? = null,
    @SerialName("video_price_min") val videoPriceMin: Int? = null,
    @SerialName("video_price_max") val videoPriceMax: Int? = null,
)

/** 一条金币流水（P24）。 */
@Serializable
data class CoinLogDto(
    val id: Long = 0,
    val type: String = "",
    val title: String = "",
    val change: Long = 0,
    @SerialName("balance_after") val balanceAfter: Long = 0,
    @SerialName("created_at") val createdAt: String? = null,
)

/** 收支明细分页。 */
@Serializable
data class CoinLogPageDto(
    val list: List<CoinLogDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    @SerialName("page_size") val pageSize: Int = 20,
    @SerialName("has_more") val hasMore: Boolean = false,
)

/** 充值档位（`recharge.packs` 的一项）。 */
@Serializable
data class RechargePackDto(
    val coins: Int = 0,
    val yuan: Int = 0,
)

/** 客服信息（后台配置）。 */
@Serializable
data class RechargeServiceDto(
    val wechat: String = "",
    @SerialName("qrcode_url") val qrcodeUrl: String = "",
    val phone: String = "",
)

/** 充值页要的一切（docs/05 第 4.7 节 `/wallet/recharge-info`，P22）。 */
@Serializable
data class RechargeInfoDto(
    val balance: Long = 0,
    val packs: List<RechargePackDto> = emptyList(),
    val service: RechargeServiceDto = RechargeServiceDto(),
    /** 合规声明（铁律 13，服务端保证非空）。 */
    @SerialName("compliance_disclosure") val complianceDisclosure: String = "",
    /** 说明文字（「选好档位后联系客服充值…」）。 */
    val notice: String = "",
)
