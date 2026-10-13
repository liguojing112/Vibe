package com.qichuang.changliao.core.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 我的资料（docs/05 第 4.2 节 `GET /user/me`）。
 *
 * 审核字段有两个值：`avatar`/`nickname`/`bio` 是**已通过**的（别人看到的，也是 `cl_user` 里的），
 * [pending] 里是**我刚提交、还没审完**的。所以 `shownXxx()` 用待审值优先 —— 自己看得到新内容。
 */
@Serializable
data class UserProfile(
    val id: Long = 0,
    val account: String = "",
    val phone: String? = null,
    val gender: Int = 0,
    val level: Int = 1,
    val nickname: String = "",
    val avatar: String = "",
    val bio: String = "",
    val birthday: String? = null,
    val age: Int? = null,
    val city: String = "",
    val height: Int? = null,
    val job: String = "",
    val education: String = "",
    @SerialName("dating_purpose") val datingPurpose: String = "",
    val tags: List<Long> = emptyList(),
    @SerialName("show_in_nearby") val showInNearby: Int = 1,
    @SerialName("show_in_recommend") val showInRecommend: Int = 1,
    @SerialName("real_name_status") val realNameStatus: Int = 0,
    @SerialName("vip_expire_at") val vipExpireAt: String? = null,
    @SerialName("is_vip") val isVip: Boolean = false,
    val photos: List<UserPhoto> = emptyList(),
    val pending: Map<String, String> = emptyMap(),
    val auditing: List<String> = emptyList(),
    @SerialName("coin_balance") val coinBalance: Long? = null,
    @SerialName("point_balance") val pointBalance: Long? = null,
    val stats: ProfileStats = ProfileStats(),
    /** 资料完善度百分比（0~100），服务端按「填了几项」算好给过来。 */
    val completeness: Int = 0,
) {
    fun shownAvatar(): String = pending[FIELD_AVATAR] ?: avatar

    fun shownNickname(): String = pending[FIELD_NICKNAME] ?: nickname

    fun shownBio(): String = pending[FIELD_BIO] ?: bio

    /** 某个字段是否在审核中（页面据此打「审核中」角标）。 */
    fun isAuditing(field: String): Boolean = field in auditing

    companion object {
        const val FIELD_AVATAR = "avatar"
        const val FIELD_NICKNAME = "nickname"
        const val FIELD_BIO = "bio"
        const val FIELD_PHOTO = "photo"
    }
}

/**
 * `/user/me` 带回的关系统计（docs/05 第 4.2 节）。
 *
 * P03 的功能入口行要显示「有 N 人喜欢了你」「有 N 人看过你的主页」和访客未读角标，
 * 这些数都在这里，不用再单独打接口。
 */
@Serializable
data class ProfileStats(
    val following: Int = 0,
    val friends: Int = 0,
    val fans: Int = 0,
    val visitors: Int = 0,
    @SerialName("unread_visitors") val unreadVisitors: Int = 0,
)

/** 相册里的一张（`audit_status`：0 待审 1 通过 2 驳回）。 */
@Serializable
data class UserPhoto(
    val id: Long = 0,
    val url: String = "",
    val sort: Int = 0,
    @SerialName("audit_status") val auditStatus: Int = 0,
    @SerialName("reject_reason") val rejectReason: String? = null,
) {
    val isApproved: Boolean get() = auditStatus == AUDIT_APPROVED
    val isRejected: Boolean get() = auditStatus == AUDIT_REJECTED

    companion object {
        const val AUDIT_PENDING = 0
        const val AUDIT_APPROVED = 1
        const val AUDIT_REJECTED = 2
    }
}

/** 相册接口的返回外壳（`POST /user/photos`）。 */
@Serializable
data class PhotoListResult(
    val photos: List<UserPhoto> = emptyList(),
)

/**
 * 要提交的资料改动。**null = 这一项不改**（服务端 `sometimes`），
 * 所以页面只填用户真正动过的字段，避免把没改的字段也送去审核。
 */
data class ProfileUpdate(
    val nickname: String? = null,
    val avatar: String? = null,
    val bio: String? = null,
    val birthday: String? = null,
    val city: String? = null,
    val height: Int? = null,
    val job: String? = null,
    val education: String? = null,
    val datingPurpose: String? = null,
    val tags: List<Long>? = null,
)
