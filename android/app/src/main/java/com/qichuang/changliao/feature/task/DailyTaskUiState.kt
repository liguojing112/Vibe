package com.qichuang.changliao.feature.task

import com.qichuang.changliao.core.data.model.DailyTask

/**
 * 每日任务（P11）状态。
 *
 * 任务列表、状态、按钮文案**全部来自服务端**（`docs/05` `/task/list`）——
 * 完成条件只有服务端算得出来（照片审核、今天发过几条消息），客户端不自己推断。
 */
data class DailyTaskUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val tasks: List<DailyTask> = emptyList(),
    /** 正在签到或领奖（按钮转圈、防连点）。 */
    val acting: Boolean = false,
    val toast: String? = null,
) {

    /** 顶部横幅（设计图 11 的原文，属于固定文案）。 */
    val bannerTitle: String get() = "今天也有好事发生 ✨"

    val bannerDesc: String get() = "完成任务，收集玫瑰，让更多同频的人看见你。"

    val isEmpty: Boolean get() = !loading && error == null && tasks.isEmpty()

    /** 还没领过奖的任务数（「去完成」的那些不算）。 */
    val claimableCount: Int get() = tasks.count { it.claimable }
}
