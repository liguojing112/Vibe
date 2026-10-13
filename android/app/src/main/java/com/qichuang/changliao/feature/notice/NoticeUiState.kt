package com.qichuang.changliao.feature.notice

import com.qichuang.changliao.core.data.model.Notice
import com.qichuang.changliao.core.data.model.NoticeCategory

/**
 * 系统消息（P10）状态。
 *
 * 两个 Tab 就是两个分类，**各自的已读是分开的**（服务端按 `category` 标已读），
 * 所以切 Tab 要重新拉一次。列表本身不缓存 —— 服务端一被拉就把这分类标已读了，
 * 缓存住会让「红点清了、列表还是旧的」对不上。
 */
data class NoticeUiState(
    val category: NoticeCategory = NoticeCategory.SYSTEM,
    val loading: Boolean = true,
    val error: String? = null,
    val notices: List<Notice> = emptyList(),
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val hasMore: Boolean = false,
    val page: Int = 1,
    /** 这一分类拉完之后还剩多少未读（服务端给的，底部角标用）。 */
    val unread: Int = 0,
) {

    val isEmpty: Boolean get() = !loading && error == null && notices.isEmpty()

    /** 当前 Tab 的文案（`docs/01` P10 的两个分类）。 */
    val title: String get() = category.label

    val nextPage: Int get() = page + 1

    companion object {
        /** 和服务端 `NoticeService::PAGE_SIZE` 一致。 */
        const val PAGE_SIZE = 20
    }
}
