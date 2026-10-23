package com.qichuang.changliao.core.data.model

/** 一条动态（P05 个人主页 / 朋友圈列表）。 */
data class Moment(
    val id: Long = 0,
    val userId: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val content: String = "",
    val images: List<String> = emptyList(),
    val likeCount: Int = 0,
    val commentCount: Int = 0,
    val isLiked: Boolean = false,
    val canDelete: Boolean = false,
    /** 毫秒 */
    val createdAt: Long = 0,
) {
    /** 点赞按钮的乐观更新：先改 UI，请求失败了再回滚。 */
    fun toggledLike(liked: Boolean): Moment = copy(
        isLiked = liked,
        likeCount = (likeCount + if (liked) 1 else -1).coerceAtLeast(0),
    )
}

data class MomentPage(
    val list: List<Moment> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val hasMore: Boolean = false,
)

data class MomentComment(
    val id: Long = 0,
    val userId: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    val content: String = "",
    val canDelete: Boolean = false,
    /** 毫秒 */
    val createdAt: Long = 0,
)

data class MomentCommentPage(
    val list: List<MomentComment> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val hasMore: Boolean = false,
)

data class MomentLikeResult(
    val liked: Boolean = false,
    val likeCount: Int = 0,
)
