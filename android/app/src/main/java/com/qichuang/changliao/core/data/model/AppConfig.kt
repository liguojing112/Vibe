package com.qichuang.changliao.core.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * App 启动配置（docs/05 第 4.1 节 `GET /api/v1/app/config`）。
 *
 * 它是长期存活的全局模型，同时要落本地缓存，所以直接用它接收接口数据，不再单列一份 DTO。
 * 所有可调数字都来自服务端（铁律 3），App 端不准自己定值。
 */
@Serializable
data class AppConfig(
    @SerialName("call_enabled") val callEnabled: Boolean = false,
    /**
     * 语音/视频的「示例价格」（各等级里的最低单价）。
     *
     * P01 的两张快捷卡、P02 的提示、P16 的横幅要一个全局示例价 —— 它们看到的是**别人**的
     * 价格，而 `/wallet/coin` 只能男用户调，所以放在启动配置里。
     * 开关关着时服务端返回 0，页面据此不显示价格。
     */
    @SerialName("voice_price_min") val voicePriceMin: Int = 0,
    @SerialName("video_price_min") val videoPriceMin: Int = 0,
    @SerialName("compliance_disclosure") val complianceDisclosure: String = "",
    val service: AppService = AppService(),
    @SerialName("chat_max_length") val chatMaxLength: Int = 0,
    @SerialName("image_max_mb") val imageMaxMb: Int = 0,
    val cities: List<String> = emptyList(),
    val tags: List<AppTag> = emptyList(),
    val version: String = "",
)

/** 客服联系方式（P22 金币充值页展示）。 */
@Serializable
data class AppService(
    val wechat: String = "",
    @SerialName("qrcode_url") val qrcodeUrl: String = "",
    val phone: String = "",
)

/** 资料标签（P14 编辑资料选标签用）。 */
@Serializable
data class AppTag(
    val id: Long = 0,
    val name: String = "",
)
