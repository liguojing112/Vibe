package com.qichuang.changliao.feature.income

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.IncomeLogEntry
import com.qichuang.changliao.core.data.model.IncomeSummary

/**
 * 我的收益（P26）状态。
 *
 * 积分、约合人民币、今日三项**全是服务端算好的**（铁律 6），这里只负责拼成要显示的文案。
 */
data class IncomeUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val summary: IncomeSummary? = null,
    val logs: List<IncomeLogEntry> = emptyList(),
    /** 正在加载下一页明细。 */
    val logsLoading: Boolean = false,
    val hasMoreLogs: Boolean = false,
    /** 已经加载到第几页（明细）。 */
    val page: Int = 1,
    val toast: String? = null,
) {

    /** 大卡上的数字：可用积分。 */
    val balanceText: String get() = (summary?.balance ?: 0).toString()

    /** 「约 ¥11.62 · 冻结中 0 积分」——约合金额由服务端给的是**分**。 */
    val balanceDesc: String
        get() = summary?.let {
            "约 ${MoneyFormat.rmb(it.yuanFen)} · 冻结中 ${MoneyFormat.points(it.frozen)}"
        } ?: ""

    val todayPointsText: String get() = MoneyFormat.signed(summary?.todayPoints ?: 0)

    val todayMessagesText: String get() = (summary?.todayMessages ?: 0).toString()

    val todayGiftsText: String get() = (summary?.todayGifts ?: 0).toString()

    val notice: String get() = summary?.notice.orEmpty()

    val isEmpty: Boolean get() = !loading && error == null && summary == null

    /** 明细为空（有 summary 但一条流水都没有）。 */
    val logsEmpty: Boolean get() = summary != null && logs.isEmpty()

    /** 下一页码（ViewModel 用）。 */
    val nextPage: Int get() = page + 1

    companion object {
        /** 和服务端 `IncomeQueryService::PAGE_SIZE` 一致，用来判断还有没有下一页。 */
        const val PAGE_SIZE = 20
    }
}
