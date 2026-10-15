package com.qichuang.changliao.feature.match

import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.DiscoverUser

/**
 * 速配 / 语音大厅的状态（docs/01 P02、P16，设计图 02、16）。
 *
 * 两个页面共用一份状态：数据来源是同一个接口（`/discover` 的 `video` / `voice` Tab），
 * 差别只在展示方式（P02 两列网格、P16 列表 + 横幅）和用哪一栏单价。
 *
 * 四种页面状态（docs/03 第 6 节第 3 条）：[loading] 加载中、[error] 出错可重试、
 * [users] 为空是空数据、有 [users] 就是内容。
 */
data class MatchUiState(
    val kind: CallKind = CallKind.VIDEO,
    val users: List<DiscoverUser> = emptyList(),
    val loading: Boolean = true,
    val error: String? = null,
    /** 男用户显示金币、女用户显示积分（和 P01 右上角胶囊一个口径）。 */
    val balanceAmount: String? = null,
    val balanceUnit: String = "币",
    /** 「示例价格」（服务端按等级配置给的最低单价），0 表示不显示价格。 */
    val priceMin: Int = 0,
) {
    val isEmpty: Boolean get() = users.isEmpty() && !loading && error == null
}
