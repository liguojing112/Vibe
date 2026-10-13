package com.qichuang.changliao.core.data.model

/**
 * 提现（P28）。
 *
 * 金额字段全是**分**（服务端按分算的手续费），页面用 `MoneyFormat.rmb()` 显示。
 */
data class WithdrawTier(
    val yuan: Int = 0,
    val points: Long = 0,
    val feeFen: Long = 0,
    val actualFen: Long = 0,
    /** 积分够不够，不够的置灰。 */
    val enabled: Boolean = false,
)

/** 收款方式（P28 的三选一）。 */
enum class WithdrawAccountType(val apiValue: String, val label: String) {
    ALIPAY("ALIPAY", "支付宝"),
    WECHAT("WECHAT", "微信"),
    BANK("BANK", "银行卡"),
    ;

    companion object {
        fun fromApi(value: String): WithdrawAccountType =
            entries.firstOrNull { it.apiValue == value } ?: ALIPAY
    }
}

/** 保存的收款信息（下次自动带出）。 */
data class WithdrawAccount(
    val accountType: WithdrawAccountType = WithdrawAccountType.ALIPAY,
    val accountNo: String = "",
    val bankName: String? = null,
)

/** 提现页要的一切。 */
data class WithdrawInfo(
    val amountYuan: Long = 0,
    val points: Long = 0,
    val frozenPoints: Long = 0,
    val tiers: List<WithdrawTier> = emptyList(),
    val feeRate: Int = 0,
    val notice: String = "",
    val realNamePassed: Boolean = false,
    /** 收款人姓名：服务端给（= 实名姓名），页面不可改。 */
    val realName: String = "",
    val needRealName: Boolean = false,
    val account: WithdrawAccount? = null,
    val dailyLimit: Int = 0,
    val todayCount: Int = 0,
    /** null = 不限次。 */
    val remainingToday: Int? = null,
) {
    /** 今天还能不能申请（次数用完就不能了）。 */
    val canApplyToday: Boolean get() = remainingToday == null || remainingToday > 0
}

/** 申请提现的结果。 */
data class WithdrawApplyResult(
    val orderNo: String = "",
    val amountFen: Long = 0,
    val points: Long = 0,
    val feeFen: Long = 0,
    val actualFen: Long = 0,
    val statusText: String = "",
    val balance: Long = 0,
    val frozen: Long = 0,
)

/** 一条提现记录（P28）。 */
data class WithdrawRecord(
    val id: Long = 0,
    val orderNo: String = "",
    val amountFen: Long = 0,
    val actualFen: Long = 0,
    val status: Int = 0,
    /** 「待审核 / 审核通过，待打款 / 已驳回 / 已打款」（服务端给）。 */
    val statusText: String = "",
    val rejectReason: String? = null,
    /** 毫秒；解析不了就是 0。 */
    val createdAt: Long = 0,
    val paidAt: Long = 0,
) {
    val rejected: Boolean get() = status == STATUS_REJECTED
    val paid: Boolean get() = status == STATUS_PAID

    companion object {
        const val STATUS_PENDING = 0
        const val STATUS_APPROVED = 1
        const val STATUS_REJECTED = 2
        const val STATUS_PAID = 3
    }
}
