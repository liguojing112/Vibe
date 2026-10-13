package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 一个每日任务（docs/05 第 4.9 节 `/task/list`）。
 *
 * **状态、进度、按钮文案、点下去干什么（`action`）全是服务端给的** ——
 * 完成条件只有服务端算得出来（照片审核、发过消息），客户端不自己推断。
 */
@Serializable
data class DailyTaskDto(
    val id: Long = 0,
    val name: String = "",
    val description: String = "",
    val icon: String = "",
    val type: String = "",
    /** 1 每日 2 一次性。 */
    val period: Int = 1,
    val target: Int = 1,
    val progress: Int = 0,
    val reward: Int = 0,
    /** 1 金币 2 积分。 */
    @SerialName("reward_type") val rewardType: Int = 1,
    /** 服务端拼好的奖励文案（「+5 金币」）。 */
    @SerialName("reward_text") val rewardText: String = "",
    /** todo / claimable / claimed。 */
    val status: String = "",
    @SerialName("button_text") val buttonText: String = "",
    /** sign_in / claim / edit_profile / discover / none。 */
    val action: String = "",
)

/** 任务列表。 */
@Serializable
data class TaskListDto(
    val list: List<DailyTaskDto> = emptyList(),
)

/** 领奖的入参。 */
@Serializable
data class TaskClaimRequestDto(
    @SerialName("task_id") val taskId: Long,
)

/**
 * 签到 / 领奖的返回。
 *
 * 签到不返回 `task_id`、领取才返回，所以这里给了默认值 —— 两个接口共用这一个 DTO。
 */
@Serializable
data class TaskRewardDto(
    @SerialName("task_id") val taskId: Long = 0,
    val reward: Int = 0,
    @SerialName("reward_type") val rewardType: Int = 1,
    @SerialName("reward_text") val rewardText: String = "",
    /** 领完之后的最新余额（金币或积分）。 */
    val balance: Long = 0,
    /** 只有签到接口会返回。 */
    val signed: Boolean = false,
)
