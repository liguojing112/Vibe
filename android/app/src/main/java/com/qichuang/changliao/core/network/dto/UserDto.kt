package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 资料接口的请求体（docs/05 第 4.2 节）。
 *
 * 所有字段都是可空的：`clJson` 配了 `explicitNulls = false`，**null 字段不会被编码**，
 * 正好对上服务端的 `sometimes` 规则（没传就不改）。注意服务端不接受 `gender`，我们也不发。
 */

@Serializable
data class UpdateProfileRequestDto(
    val nickname: String? = null,
    val avatar: String? = null,
    val bio: String? = null,
    val birthday: String? = null,
    val city: String? = null,
    val height: Int? = null,
    val job: String? = null,
    val education: String? = null,
    @SerialName("dating_purpose") val datingPurpose: String? = null,
    val tags: List<Long>? = null,
)

/** 相册整体提交：顺序即展示顺序，空数组 = 清空。 */
@Serializable
data class UpdatePhotosRequestDto(
    val urls: List<String>,
)

@Serializable
data class UpdateSettingsRequestDto(
    @SerialName("show_in_nearby") val showInNearby: Int? = null,
    @SerialName("show_in_recommend") val showInRecommend: Int? = null,
)

/** 上传成功后的 `data`。 */
@Serializable
data class UploadResultDto(
    val url: String = "",
)
