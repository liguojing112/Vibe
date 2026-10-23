package com.qichuang.changliao.feature.moment

import com.qichuang.changliao.core.data.model.MomentComment

/** 评论底部弹窗的状态。 */
data class MomentCommentsUiState(
    val momentId: Long = 0,
    val loading: Boolean = true,
    val error: String? = null,
    val comments: List<MomentComment> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
    val hasMore: Boolean = false,
    val loadingMore: Boolean = false,
    val input: String = "",
    val sending: Boolean = false,
    val toast: String? = null,
) {

    val isEmpty: Boolean get() = !loading && error == null && comments.isEmpty()

    val canSend: Boolean get() = input.isNotBlank() && !sending

    val nextPage: Int get() = page + 1
}
