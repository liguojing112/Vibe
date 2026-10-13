package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/** 一个提现档位（P28）。`enabled` = 积分够不够，不够的置灰。 */
@Serializable
data class WithdrawTierDto(
    val yuan: Int = 0,
    val points: Long = 0,
    @SerialName("fee_fen") val feeFen: Long = 0,
    @SerialName("actual_fen") val actualFen: Long = 0,
    val enabled: Boolean = false,
)

/** 保存的收款信息（P28 下次自动带出）。 */
@Serializable
data class WithdrawAccountDto(
    @SerialName("account_type") val accountType: String = "",
    @SerialName("account_no") val accountNo: String = "",
    @SerialName("bank_name") val bankName: String? = null,
)

/** 提现页要的一切（P28）。 */
@Serializable
data class WithdrawInfoDto(
    /** 可提现金额（元，整数）。 */
    @SerialName("amount_yuan") val amountYuan: Long = 0,
    val points: Long = 0,
    @SerialName("frozen_points") val frozenPoints: Long = 0,
    val tiers: List<WithdrawTierDto> = emptyList(),
    /** 手续费（万分比）。 */
    @SerialName("fee_rate") val feeRate: Int = 0,
    val notice: String = "",
    @SerialName("real_name_status") val realNameStatus: Int = 0,
    @SerialName("real_name_passed") val realNamePassed: Boolean = false,
    /** 收款人姓名：**必须与实名一致**，服务端给，页面不可改。 */
    @SerialName("real_name") val realName: String = "",
    @SerialName("need_real_name") val needRealName: Boolean = false,
    val account: WithdrawAccountDto? = null,
    @SerialName("daily_limit") val dailyLimit: Int = 0,
    @SerialName("today_count") val todayCount: Int = 0,
    /** 0 = 不限次；这时没有「还剩几次」这回事。 */
    @SerialName("remaining_today") val remainingToday: Int? = null,
)

/** 申请提现的入参。 */
@Serializable
data class WithdrawApplyRequestDto(
    @SerialName("amount_yuan") val amountYuan: Int,
    @SerialName("account_type") val accountType: String,
    @SerialName("account_no") val accountNo: String,
    @SerialName("bank_name") val bankName: String? = null,
)

/** 申请提现的返回。 */
@Serializable
data class WithdrawApplyDataDto(
    @SerialName("order_no") val orderNo: String = "",
    @SerialName("amount_fen") val amountFen: Long = 0,
    val points: Long = 0,
    @SerialName("fee_fen") val feeFen: Long = 0,
    @SerialName("actual_fen") val actualFen: Long = 0,
    val status: Int = 0,
    @SerialName("status_text") val statusText: String = "",
    val balance: Long = 0,
    val frozen: Long = 0,
)

/** 一条提现记录（P28）。 */
@Serializable
data class WithdrawRecordDto(
    val id: Long = 0,
    @SerialName("order_no") val orderNo: String = "",
    @SerialName("amount_fen") val amountFen: Long = 0,
    val points: Long = 0,
    @SerialName("fee_fen") val feeFen: Long = 0,
    @SerialName("actual_fen") val actualFen: Long = 0,
    @SerialName("account_type") val accountType: String = "",
    @SerialName("account_no") val accountNo: String = "",
    @SerialName("account_name") val accountName: String = "",
    val status: Int = 0,
    @SerialName("status_text") val statusText: String = "",
    @SerialName("reject_reason") val rejectReason: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    @SerialName("paid_at") val paidAt: String? = null,
)

/** 提现记录分页。 */
@Serializable
data class WithdrawRecordPageDto(
    val list: List<WithdrawRecordDto> = emptyList(),
    val total: Int = 0,
    val page: Int = 1,
)
