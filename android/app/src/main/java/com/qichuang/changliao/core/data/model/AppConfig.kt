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
