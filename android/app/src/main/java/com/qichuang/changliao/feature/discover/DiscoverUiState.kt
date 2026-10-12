package com.qichuang.changliao.feature.discover

import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.DiscoverUser

/**
 * 发现页的三个 Tab（docs/01 P01「开关关闭」时的形态）。
 *
 * 开关打开的形态会多「视频」「语音」两个 Tab、多两张快捷卡、卡片上多语音/视频按钮 ——
 * 本期不做（客户 2026-10-12 确认），要做时见 PROGRESS 里 P8 的说明（服务端 `/discover`
 * 也得跟着支持 `tab=video|voice`）。
 */
enum class DiscoverTab(val label: String, val apiValue: String) {
    RECOMMEND("推荐", "recommend"),
    NEW("新人", "new"),
    CITY("同城", "city"),
}

/**
 * 发现页状态（P01）。
 *
 * 四种页面状态（docs/03 第 6 节第 3 条）：[loading] 加载中、[error] 出错可重试、
 * [isEmpty] 空数据、有 [users] 就是内容。列表的分页失败单独放在 [loadMoreError]，不覆盖整页。
 */
data class DiscoverUiState(
    val tabs: List<DiscoverTab> = DiscoverTab.entries,
    val tab: DiscoverTab = DiscoverTab.RECOMMEND,
    val users: List<DiscoverUser> = emptyList(),
    val hasMore: Boolean = false,
    val loading: Boolean = true,
    val refreshing: Boolean = false,
    val loadingMore: Boolean = false,
    val loadMoreError: String? = null,
    val error: String? = null,
    val filter: DiscoverFilter = DiscoverFilter(),
    /** 男用户显示金币、女用户显示积分（docs/01 P01 右上角胶囊） */
    val balanceAmount: String? = null,
    val balanceUnit: String = "币",
    val toast: String? = null,
) {
    val isEmpty: Boolean get() = users.isEmpty() && !loading && error == null
}
