package com.qichuang.changliao.core.data.model

/**
 * 我的收益（P26）。所有数字都是服务端算好的（铁律 6）：
 * [yuanFen] 是**分**，客户端只负责显示。
 */
data class IncomeSummary(
    val balance: Long = 0,
    val frozen: Long = 0,
    val yuanFen: Long = 0,
    val todayPoints: Long = 0,
    val todayMessages: Int = 0,
    val todayGifts: Int = 0,
    /** 底部说明（服务端给文案）。 */
    val notice: String = "",
)

/**
 * 一条积分流水（P26 的收益明细）。
 *
 * [type] 是服务端 `cl_point_log.type` 的原值（MESSAGE_INCOME / GIFT_INCOME / TASK /
 * WITHDRAW_FREEZE / WITHDRAW_REFUND / WITHDRAW_PAID / ADMIN_ADJUST），页面用它挑图标；
 * 文案用服务端拼好的 [title]。
 */
data class IncomeLogEntry(
    val id: Long = 0,
    val type: String = "",
    val title: String = "",
    /** 可用积分的变化（正数进账、负数冻结）。 */
    val change: Long = 0,
    /** 冻结积分的变化。 */
    val frozenChange: Long = 0,
    val balanceAfter: Long = 0,
    /** 毫秒；解析不了就是 0。 */
    val createdAt: Long = 0,
) {
    /** 提现相关的三种类型是「出账」，不显示成收益。 */
    val isWithdraw: Boolean get() = type.startsWith("WITHDRAW_")
}

/** 等级表的一行（P27）。 */
data class IncomeLevelRow(
    val level: Int = 1,
    val name: String = "",
    val thresholdPoints: Long = 0,
    val messagePrice: Int = 0,
    val messageShareRate: Int = 0,
)

/**
 * 我的等级与收费（P27，只读）。
 *
 * [shareRate] 是**等级表里写的**分成（万分比），[actualShareRate] 是**我实际能拿的** ——
 * 未实名时后者是前者的一半，页面要按 P27 的要求说明这一点。
 */
data class IncomeLevel(
    val level: Int = 1,
    val name: String = "",
    val totalEarnedPoints: Long = 0,
    val levelLocked: Boolean = false,
    val nextLevel: Int? = null,
    val nextThreshold: Long? = null,
    val pointsToNext: Long = 0,
    val progressPercent: Int = 0,
    val messagePrice: Int = 0,
    val shareRate: Int = 0,
    val actualShareRate: Int = 0,
    val giftShareRate: Int = 0,
    val realNamePassed: Boolean = false,
    val callEnabled: Boolean = false,
    val voicePrice: Int = 0,
    val videoPrice: Int = 0,
    val levels: List<IncomeLevelRow> = emptyList(),
)
