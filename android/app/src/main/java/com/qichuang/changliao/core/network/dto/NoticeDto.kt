package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 一条系统/互动通知（docs/05 第 4.9 节 `/notice/list`，P10）。
 *
 * [read] 是「**打开这一页之前**读没读过」—— 接口在返回列表之后才把这一分类标为已读。
 */
@Serializable
data class NoticeDto(
    val id: Long = 0,
    val category: Int = 1,
    val title: String = "",
    val content: String = "",
    @SerialName("biz_type") val bizType: String? = null,
    @SerialName("biz_id") val bizId: Long? = null,
    val read: Boolean = false,
    @SerialName("created_at") val createdAt: String? = null,
)

/** 通知列表一页。[unread] 是标已读**之后**的剩余未读总数（底部角标用）。 */
@Serializable
data class NoticePageDto(
    val list: List<NoticeDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val unread: Int = 0,
)

/** 举报的入参（docs/05 第 4.9 节 `/report`）。说明和截图都可选。 */
@Serializable
data class ReportRequestDto(
    @SerialName("target_id") val targetId: Long,
    val reason: String,
    val content: String = "",
    val images: List<String> = emptyList(),
)

/** 举报的返回（只提交，处理在后台）。 */
@Serializable
data class ReportDataDto(
    val id: Long = 0,
    val status: Int = 0,
    @SerialName("status_text") val statusText: String = "",
)
