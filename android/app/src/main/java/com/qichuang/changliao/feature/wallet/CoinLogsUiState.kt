package com.qichuang.changliao.feature.wallet

import com.qichuang.changliao.core.data.model.CoinLogEntry
import com.qichuang.changliao.core.data.model.CoinLogTab

/**
 * 金币收支明细（P24）状态。
 *
 * 分页失败单独放 [loadMoreError]，不覆盖整页（docs/03 第 6 节第 3 条）。
 */
data class CoinLogsUiState(
    val tab: CoinLogTab = CoinLogTab.ALL,
    val entries: List<CoinLogEntry> = emptyList(),
    val balance: Long = 0,
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val hasMore: Boolean = false,
    val error: String? = null,
    val loadMoreError: String? = null,
) {
    val isEmpty: Boolean get() = !loading && error == null && entries.isEmpty()

    val emptyTitle: String
        get() = when (tab) {
            CoinLogTab.ALL -> "还没有收支记录"
            CoinLogTab.RECHARGE -> "还没有充值记录"
            CoinLogTab.SPEND -> "还没有消费记录"
        }
}
