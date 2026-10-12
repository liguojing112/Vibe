package com.qichuang.changliao.feature.relation

import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.RelationKind
import com.qichuang.changliao.core.data.model.RelationPerson

/**
 * 「喜欢我的人」和「最近访客」两个页面除了接口和文案以外完全一样，所以共用一套状态与页面
 * （docs/01 P08、P09）。
 */
data class RelationListUiState(
    val kind: RelationKind = RelationKind.VISITORS,
    val loading: Boolean = true,
    val error: String? = null,
    val people: List<RelationPerson> = emptyList(),
    val total: Int = 0,
    val hasMore: Boolean = false,
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val loadMoreError: String? = null,
    /** 服务端说这页是打码数据（非会员男用户，或女用户且开关关掉）。 */
    val locked: Boolean = false,
    val vipPromptVisible: Boolean = false,
    val submitting: Boolean = false,
    val toast: String? = null,
) {
    val isEmpty: Boolean get() = people.isEmpty() && !loading && error == null

    val title: String get() = kind.title

    /** 顶部提示卡（P08：「有 N 人看过你的主页 ✨」）。没人时不给这张卡。 */
    val noticeTitle: String?
        get() = when {
            total <= 0 -> null
            kind == RelationKind.VISITORS -> "有 $total 人看过你的主页 ✨"
            else -> "有 $total 人喜欢你 ✨"
        }

    val noticeContent: String?
        get() = if (locked) "开通会员查看全部" else null
}

/** 一行下面的说明文字：访客是「今天看了你的资料 · 上海」，喜欢我的人是「关注了你 · 上海」。 */
internal fun relationDesc(kind: RelationKind, person: RelationPerson): String {
    val action = when (kind) {
        RelationKind.VISITORS -> person.visitedAt
            ?.let { TimeFormat.parse(it) }
            ?.let { TimeFormat.dayLabel(it) + "看了你的资料" }
            ?: "看了你的资料"

        RelationKind.FANS -> "关注了你"
    }

    return listOfNotNull(action, person.city.ifBlank { null }).joinToString(" · ")
}

/** 右侧小按钮：访客是「查看」；喜欢我的人没互关时是「回关」（docs/01 P09）。 */
internal fun relationActionLabel(kind: RelationKind, person: RelationPerson): String = when {
    kind == RelationKind.VISITORS -> "查看"
    person.isMutual -> "查看"
    else -> "回关"
}
