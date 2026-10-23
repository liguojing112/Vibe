package com.qichuang.changliao.core.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 他人主页（docs/05 第 4.2 节 `/user/{id}`、docs/01 P05）。
 *
 * 这里拿到的**永远是对方审核通过的内容**，相册也只有已通过的；
 * [price] 只在「男用户看女用户」时才有。
 */
@Serializable
data class UserDetail(
    val id: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val age: Int? = null,
    val city: String = "",
    val height: Int? = null,
    val job: String = "",
    val education: String = "",
    @SerialName("dating_purpose") val datingPurpose: String = "",
    val bio: String = "",
    val tags: List<Long> = emptyList(),
    val level: Int = 1,
    val online: Boolean = false,
    @SerialName("is_new") val isNew: Boolean = false,
    val verified: Boolean = false,
    val photos: List<UserPhoto> = emptyList(),
    @SerialName("is_following") val isFollowing: Boolean = false,
    @SerialName("is_mutual") val isMutual: Boolean = false,
    @SerialName("is_blocked") val isBlocked: Boolean = false,
    val price: UserDetailPrice? = null,
    @SerialName("moment_count") val momentCount: Int = 0,
    @SerialName("latest_moments") val latestMoments: List<MomentPreview> = emptyList(),
) {
    /** 封面用第一张相册，没有相册就退回头像（docs/01 P05）。 */
    val coverUrl: String get() = photos.firstOrNull()?.url ?: avatar

    /** 朋友圈区块的缩略图：把最近几条的图片拉平，最多 3 张。 */
    val momentPreviewUrls: List<String>
        get() = latestMoments.flatMap { it.images }.take(3)
}

/** 个人主页上的朋友圈预览（只要展示，不需要点赞数这些）。 */
@Serializable
data class MomentPreview(
    val id: Long = 0,
    val images: List<String> = emptyList(),
    val content: String = "",
    @SerialName("created_at") val createdAt: String? = null,
)

/** 聊天价格（docs/01 P05）：开关关着时没有语音/视频两项。 */
@Serializable
data class UserDetailPrice(
    @SerialName("message_price") val messagePrice: Long = 0,
    @SerialName("free_hint") val freeHint: String? = null,
    @SerialName("voice_price") val voicePrice: Long? = null,
    @SerialName("video_price") val videoPrice: Long? = null,
)
