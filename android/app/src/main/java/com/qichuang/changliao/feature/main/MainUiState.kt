package com.qichuang.changliao.feature.main

import androidx.compose.ui.graphics.vector.ImageVector
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.navigation.Routes

/**
 * 底部导航的 4 个 Tab（docs/01 第 3 节）。
 *
 * 顺序就是显示顺序；[MATCH] 只在 `feature.call_enabled` 打开时出现。
 */
enum class MainTab(
    val label: String,
    val route: String,
    val icon: ImageVector,
) {
    DISCOVER("遇见", Routes.TAB_DISCOVER, ClIcons.home),
    MATCH("速配", Routes.TAB_MATCH, ClIcons.video),
    MESSAGE("消息", Routes.TAB_MESSAGE, ClIcons.message),
    MINE("我", Routes.TAB_MINE, ClIcons.user),
    ;

    companion object {
        fun fromRoute(route: String): MainTab? = entries.firstOrNull { it.route == route }
    }
}

/** 主界面状态：[tabs] 已按开关过滤过，[selected] 一定在 [tabs] 里，[badges] 只含非 0 的角标。 */
data class MainUiState(
    val tabs: List<MainTab>,
    val selected: MainTab,
    val badges: Map<String, Int>,
)
