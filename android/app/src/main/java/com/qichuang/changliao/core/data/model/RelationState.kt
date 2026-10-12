package com.qichuang.changliao.core.data.model

/** 我和某个人的关注关系（docs/01 P05 的「关注 / 已关注 / 互相关注」）。 */
data class RelationState(
    val isFollowing: Boolean = false,
    val isMutual: Boolean = false,
) {
    companion object {
        val NONE = RelationState()
    }
}
