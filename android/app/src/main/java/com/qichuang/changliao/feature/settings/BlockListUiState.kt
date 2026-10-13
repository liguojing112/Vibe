package com.qichuang.changliao.feature.settings

import com.qichuang.changliao.core.data.model.RelationPerson

/**
 * 黑名单（P15 里的一个入口）状态。
 *
 * 列表**不缓存**：移除之后要立刻少一个人，缓存住会让刚移除的人还在列表里。
 */
data class BlockListUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val people: List<RelationPerson> = emptyList(),
    /** 正在解除拉黑的那个人（按钮转圈、防连点）。 */
    val removingId: Long? = null,
    val toast: String? = null,
) {

    val isEmpty: Boolean get() = !loading && error == null && people.isEmpty()
}
