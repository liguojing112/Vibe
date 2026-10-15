package com.qichuang.changliao.feature.discover

import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.DiscoverUser

/**
 * 发现页的 Tab（docs/01 P01、第 4 节）。
 *
 * 前三个一直都在；[VIDEO] / [VOICE] 只在 `feature.call_enabled` 打开时出现
 * （开关关着时客户端连这两个 Tab 都不显示，见 `visibleTabs`）。
 *
 * [VIDEO] / [VOICE] 在服务端取的是同一批人（「现在能接」= 开着在线接听 + 最近活跃过），
 * 差别只在卡片上显示哪一栏单价 —— 库里没有「只接语音 / 只接视频」这个字段，
 * 见 `DiscoverRepository::applyTab` 的说明和 PROGRESS 的 Q0-23。
 */
enum class DiscoverTab(val label: String, val apiValue: String) {
    RECOMMEND("推荐", "recommend"),
    NEW("新人", "new"),
    CITY("同城", "city"),
    VIDEO("视频", "video"),
    VOICE("语音", "voice"),
    ;

    companion object {
        /** 开关关着时能看到的 Tab（顺序就是显示顺序）。 */
        val basic: List<DiscoverTab> = listOf(RECOMMEND, NEW, CITY)

        /** 开关打开时多出来的两个。 */
        val call: List<DiscoverTab> = listOf(VIDEO, VOICE)

        fun visibleTabs(callEnabled: Boolean): List<DiscoverTab> =
            if (callEnabled) basic + call else basic
    }
}

/**
 * 发现页状态（P01）。
 *
 * 四种页面状态（docs/03 第 6 节第 3 条）：[loading] 加载中、[error] 出错可重试、
 * [isEmpty] 空数据、有 [users] 就是内容。列表的分页失败单独放在 [loadMoreError]，不覆盖整页。
 */
data class DiscoverUiState(
    val tabs: List<DiscoverTab> = DiscoverTab.basic,
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
    /** `feature.call_enabled`（docs/01 第 4 节）：决定两张快捷卡和卡片上的语音/视频按钮显不显示。 */
    val callEnabled: Boolean = false,
    /** 快捷卡上的「示例价格」（服务端按等级配置给的最低单价），0 表示不显示价格。 */
    val voicePriceMin: Int = 0,
    val videoPriceMin: Int = 0,
    val toast: String? = null,
) {
    val isEmpty: Boolean get() = users.isEmpty() && !loading && error == null
}
