package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 我的收益（docs/05 第 4.8 节 `/income/summary`，P26）。
 *
 * `yuan_fen` 是**分**：客户端用 `MoneyFormat.rmb()` 直接显示「¥11.62」——
 * 换算在服务端做（铁律 6），客户端不做任何除法。
 */
@Serializable
data class IncomeSummaryDto(
    val balance: Long = 0,
    val frozen: Long = 0,
    @SerialName("yuan_fen") val yuanFen: Long = 0,
    @SerialName("today_points") val todayPoints: Long = 0,
    @SerialName("today_messages") val todayMessages: Int = 0,
    @SerialName("today_gifts") val todayGifts: Int = 0,
    val notice: String = "",
)

/** 一条积分流水（P26 的收益明细）。 */
@Serializable
data class IncomeLogDto(
    val id: Long = 0,
    val type: String = "",
    val title: String = "",
    val change: Long = 0,
    @SerialName("frozen_change") val frozenChange: Long = 0,
    @SerialName("balance_after") val balanceAfter: Long = 0,
    @SerialName("created_at") val createdAt: String? = null,
)

/** 收益明细分页。 */
@Serializable
data class IncomeLogPageDto(
    val list: List<IncomeLogDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
)

/** 等级表的一行（P27）。 */
@Serializable
data class IncomeLevelRowDto(
    val level: Int = 1,
    val name: String = "",
    @SerialName("threshold_points") val thresholdPoints: Long = 0,
    @SerialName("message_price") val messagePrice: Int = 0,
    @SerialName("message_share_rate") val messageShareRate: Int = 0,
)

/** 我的等级与收费（P27）。单价和分成都是服务端现算的，客户端只显示。 */
@Serializable
data class IncomeLevelDto(
    val level: Int = 1,
    val name: String = "",
    @SerialName("total_earned_points") val totalEarnedPoints: Long = 0,
    @SerialName("level_locked") val levelLocked: Boolean = false,
    @SerialName("next_level") val nextLevel: Int? = null,
    @SerialName("next_threshold") val nextThreshold: Long? = null,
    @SerialName("points_to_next") val pointsToNext: Long = 0,
    @SerialName("progress_percent") val progressPercent: Int = 0,
    @SerialName("message_price") val messagePrice: Int = 0,
    @SerialName("share_rate") val shareRate: Int = 0,
    @SerialName("actual_share_rate") val actualShareRate: Int = 0,
    @SerialName("gift_share_rate") val giftShareRate: Int = 0,
    @SerialName("real_name_passed") val realNamePassed: Boolean = false,
    @SerialName("call_enabled") val callEnabled: Boolean = false,
    @SerialName("voice_price") val voicePrice: Int = 0,
    @SerialName("video_price") val videoPrice: Int = 0,
    val levels: List<IncomeLevelRowDto> = emptyList(),
)
