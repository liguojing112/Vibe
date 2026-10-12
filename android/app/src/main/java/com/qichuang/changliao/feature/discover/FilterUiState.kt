package com.qichuang.changliao.feature.discover

import com.qichuang.changliao.core.data.model.DiscoverFilter

/**
 * 筛选条件页状态（docs/01 P07）。
 *
 * [draft] 是页面上正在改的那份；点「查看匹配的人」才存到本地（回到 P01 生效）。
 */
data class FilterUiState(
    val loading: Boolean = true,
    val loadError: String? = null,
    val draft: DiscoverFilter = DiscoverFilter(),
    val cities: List<String> = emptyList(),
    /** 保存成功、可以回上一页了。 */
    val applied: Boolean = false,
    val toast: String? = null,
) {
    val purposes: List<String> get() = DiscoverFilter.PURPOSES

    val cityOptions: List<String> get() = listOf(ALL_CITIES) + cities

    /** 「全部」+ 城市列表里的当前选中项。 */
    val selectedCities: Set<String>
        get() = setOf(draft.city.ifEmpty { ALL_CITIES })

    val selectedPurposes: Set<String> get() = draft.purposes.toSet()

    /** 认证状态是多选，但底层是两个开关（docs/01 P07）。 */
    val selectedVerifications: Set<String>
        get() = buildSet {
            if (draft.verified) add(VERIFIED_REAL_NAME)
            if (draft.complete) add(VERIFIED_COMPLETE)
        }

    companion object {
        /** 「全部城市」这个 Chip（选中它 = 不按城市筛）。 */
        const val ALL_CITIES = "全部"

        const val VERIFIED_REAL_NAME = "真人认证"
        const val VERIFIED_COMPLETE = "资料完善"
    }
}
