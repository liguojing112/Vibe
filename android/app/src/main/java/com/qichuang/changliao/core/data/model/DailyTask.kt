package com.qichuang.changliao.core.data.model

/**
 * 一个每日任务（P11）。
 *
 * 状态和按钮文案都是服务端算好给的（完成条件只有服务端知道），客户端只按 [action] 决定点了干什么。
 */
data class DailyTask(
    val id: Long = 0,
    val name: String = "",
    val description: String = "",
    val icon: String = "",
    val type: String = "",
    val period: Int = PERIOD_DAILY,
    val target: Int = 1,
    val progress: Int = 0,
    val reward: Int = 0,
    val rewardType: Int = REWARD_COIN,
    /** 「+5 金币」/「+20 积分」。 */
    val rewardText: String = "",
    val status: String = STATUS_TODO,
    /** 「签到」「领取」「去完成」「去认识」「已完成」。 */
    val buttonText: String = "",
    val action: String = ACTION_NONE,
) {

    /** 已领过奖励（按钮变灰不可点）。 */
    val claimed: Boolean get() = status == STATUS_CLAIMED

    /** 完成了、可以领了。 */
    val claimable: Boolean get() = status == STATUS_CLAIMABLE

    /** 「1/3」这种进度文案；目标就是 1 的任务不显示（「0/1」没意义）。 */
    val progressText: String get() = if (target > 1) "$progress/$target" else ""

    companion object {
        const val STATUS_TODO = "todo"
        const val STATUS_CLAIMABLE = "claimable"
        const val STATUS_CLAIMED = "claimed"

        const val ACTION_SIGN_IN = "sign_in"
        const val ACTION_CLAIM = "claim"
        const val ACTION_EDIT_PROFILE = "edit_profile"
        const val ACTION_DISCOVER = "discover"
        const val ACTION_NONE = "none"

        const val PERIOD_DAILY = 1
        const val REWARD_COIN = 1
    }
}

/** 签到 / 领奖的结果：奖励文案 + 最新余额。 */
data class TaskReward(
    val reward: Int = 0,
    val rewardType: Int = DailyTask.REWARD_COIN,
    /** 「+5 金币」。 */
    val rewardText: String = "",
    /** 领完之后的最新余额（金币或积分）。 */
    val balance: Long = 0,
)
