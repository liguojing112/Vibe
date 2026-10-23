package com.qichuang.changliao.feature.moment

import com.qichuang.changliao.core.data.model.Moment

/**
 * 朋友圈列表（P05 个人主页的「查看全部」/「我的朋友圈」）。
 *
 * `userId = 0` 表示「我的」—— 走 `/moment/mine`，其余走 `/moment/user/{id}`。
 */
data class MomentUiState(
    val userId: Long = 0,
    val loading: Boolean = true,
    val error: String? = null,
    val moments: List<Moment> = emptyList(),
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val hasMore: Boolean = false,
    val page: Int = 1,
    /** 大图预览：当前显示第几张 + 这一组图的地址。 */
    val previewUrls: List<String> = emptyList(),
    val previewIndex: Int? = null,
    val toast: String? = null,
) {

    val isMine: Boolean get() = userId == 0L

    val title: String get() = if (isMine) "我的朋友圈" else "朋友圈"

    val isEmpty: Boolean get() = !loading && error == null && moments.isEmpty()

    val nextPage: Int get() = page + 1

    companion object {
        /** 和服务端 `MomentService::PAGE_SIZE` 一致。 */
        const val PAGE_SIZE = 10
    }
}
