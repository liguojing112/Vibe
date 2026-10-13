package com.qichuang.changliao.feature.withdraw

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.WithdrawAccountType
import com.qichuang.changliao.core.data.model.WithdrawInfo
import com.qichuang.changliao.core.data.model.WithdrawRecord
import com.qichuang.changliao.core.data.model.WithdrawTier

/**
 * 收益提现（P28）状态。
 *
 * 档位、可提现金额、今日剩余次数、收款人姓名**全部来自服务端** ——
 * 收款人必须是实名姓名，页面只显示不可改（`docs/01` P28）。
 */
data class WithdrawUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val info: WithdrawInfo? = null,
    val selectedYuan: Int? = null,
    val accountType: WithdrawAccountType = WithdrawAccountType.ALIPAY,
    val accountNo: String = "",
    val bankName: String = "",
    val submitting: Boolean = false,
    val records: List<WithdrawRecord> = emptyList(),
    val recordsLoading: Boolean = false,
    val hasMoreRecords: Boolean = false,
    val recordsPage: Int = 1,
    val toast: String? = null,
) {

    val selectedTier: WithdrawTier? get() = info?.tiers?.firstOrNull { it.yuan == selectedYuan }

    /** 可提现金额（元 → 「¥62.80」）。 */
    val amountText: String get() = MoneyFormat.rmb((info?.amountYuan ?: 0) * 100)

    /** 「6280 积分」。 */
    val pointsText: String get() = MoneyFormat.points(info?.points ?: 0)

    /** 未实名时不能提交，按钮变成去实名（`docs/01` P28）。 */
    val realNameMissing: Boolean
        get() = info != null && info.needRealName && !info.realNamePassed

    val buttonText: String get() = if (realNameMissing) "先完成实名认证" else "提交提现申请"

    val needBankName: Boolean get() = accountType == WithdrawAccountType.BANK

    /** 收款人姓名：服务端给（= 实名姓名），没实名时是空的。 */
    val accountNameText: String get() = info?.realName.orEmpty()

    val canSubmit: Boolean
        get() = info != null && !submitting && !realNameMissing &&
            selectedYuan != null && accountNo.isNotBlank() &&
            (!needBankName || bankName.isNotBlank())

    /** 「今日还可申请 N 次」；不限次时说「不限次数」。 */
    val quotaText: String
        get() = when {
            info == null -> ""
            info.remainingToday == null -> "每日不限次数"
            info.remainingToday > 0 -> "今日还可申请 ${info.remainingToday} 次"
            else -> "今天的次数已经用完了"
        }

    /** 规则说明：次数 + 手续费 + 到账说明（都来自服务端配置）。 */
    val ruleText: String
        get() = info?.let {
            val fee = if (it.feeRate <= 0) {
                "免手续费"
            } else {
                "手续费 ${MoneyFormat.percent(it.feeRate)}"
            }
            "$quotaText · $fee · ${it.notice}"
        }.orEmpty()

    val isEmpty: Boolean get() = !loading && error == null && info == null

    val recordsEmpty: Boolean get() = info != null && records.isEmpty()

    val nextRecordsPage: Int get() = recordsPage + 1

    companion object {
        /** 和服务端 `WithdrawService::PAGE_SIZE` 一致。 */
        const val PAGE_SIZE = 20
    }
}
