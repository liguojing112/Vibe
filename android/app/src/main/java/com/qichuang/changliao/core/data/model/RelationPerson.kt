package com.qichuang.changliao.core.data.model

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 关系列表（「喜欢我的人」「最近访客」）里的一个人（docs/05 第 4.4 节）。
 *
 * 非会员看到的 [nickname] 是服务端打码过的（只留首字 + `*`），[avatarBlur] 告诉客户端把头像糊掉；
 * 这时整页的 [RelationPage.locked] 也是 true。
 */
@Serializable
data class RelationPerson(
    val id: Long = 0,
    val nickname: String = "",
    val avatar: String = "",
    @SerialName("avatar_blur") val avatarBlur: Boolean = false,
    val city: String = "",
    val age: Int? = null,
    val level: Int = 1,
    @SerialName("is_following") val isFollowing: Boolean = false,
    @SerialName("is_mutual") val isMutual: Boolean = false,
    /** 只看「最近访客」有：对方最近一次来看的时间（`yyyy-MM-dd HH:mm:ss`）。 */
    @SerialName("visited_at") val visitedAt: String? = null,
)

/** 关系列表的一页（分页外壳 + 一个 `locked` 表示这页是不是打码数据）。 */
@Serializable
data class RelationPage(
    val list: List<RelationPerson> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    @SerialName("page_size") val pageSize: Int = 20,
    @SerialName("has_more") val hasMore: Boolean = false,
    val locked: Boolean = false,
)

/** 要拉哪个关系列表（两个页面除了接口和文案以外完全一样）。 */
enum class RelationKind(val title: String) {
    /** P08 最近访客。 */
    VISITORS("最近访客"),

    /** P09 喜欢我的人。 */
    FANS("喜欢我的人"),
}
