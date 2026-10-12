package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** 关系接口的入参（docs/05 第 4.4 节）：关注 / 取关 / 拉黑 / 解除拉黑都是传 `target_id`。 */
@Serializable
data class RelationTargetDto(
    @SerialName("target_id") val targetId: Long,
)

/** 关注 / 取关后返回的关系快照（互关的按钮文案不一样，客户端不用自己猜）。 */
@Serializable
data class RelationStateDto(
    @SerialName("is_following") val isFollowing: Boolean = false,
    @SerialName("is_mutual") val isMutual: Boolean = false,
)
