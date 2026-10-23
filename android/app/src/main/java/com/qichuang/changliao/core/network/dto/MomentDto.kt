package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** 一条动态（服务端 moment 接口返回的 item）。 */
@Serializable
data class MomentDto(
    val id: Long = 0,
    @SerialName("user_id") val userId: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val content: String = "",
    val images: List<String> = emptyList(),
    @SerialName("like_count") val likeCount: Int = 0,
    @SerialName("comment_count") val commentCount: Int = 0,
    @SerialName("is_liked") val isLiked: Boolean = false,
    @SerialName("can_delete") val canDelete: Boolean = false,
    @SerialName("created_at") val createdAt: String? = null,
)

@Serializable
data class MomentPageDto(
    val list: List<MomentDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    @SerialName("page_size") val pageSize: Int = 10,
    @SerialName("has_more") val hasMore: Boolean = false,
)

/** 一条评论。 */
@Serializable
data class MomentCommentDto(
    val id: Long = 0,
    @SerialName("user_id") val userId: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val content: String = "",
    @SerialName("can_delete") val canDelete: Boolean = false,
    @SerialName("created_at") val createdAt: String? = null,
)

@Serializable
data class MomentCommentPageDto(
    val list: List<MomentCommentDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    @SerialName("page_size") val pageSize: Int = 10,
    @SerialName("has_more") val hasMore: Boolean = false,
)

/** 点赞 / 取消点赞的结果。 */
@Serializable
data class MomentLikeResultDto(
    val liked: Boolean = false,
    @SerialName("like_count") val likeCount: Int = 0,
)

@Serializable
data class PublishMomentRequestDto(
    val content: String = "",
    val images: List<String> = emptyList(),
)

@Serializable
data class CreateMomentCommentRequestDto(
    val content: String,
)
