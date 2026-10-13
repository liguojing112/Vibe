package com.qichuang.changliao.feature.main

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.qichuang.changliao.BuildConfig
import com.qichuang.changliao.core.designsystem.component.ClBottomBar
import com.qichuang.changliao.core.designsystem.component.ClBottomBarItem
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.navigation.Routes
import com.qichuang.changliao.feature.common.DevEntriesPage
import com.qichuang.changliao.feature.common.DevEntry
import com.qichuang.changliao.feature.common.PlaceholderPage
import com.qichuang.changliao.feature.discover.DiscoverRoute
import com.qichuang.changliao.feature.discover.DiscoverTab
import com.qichuang.changliao.feature.message.MessageRoute

/**
 * 主界面：底部导航 + Tab 内容（docs/01 第 3 节）。
 *
 * 「速配」Tab 由 `feature.call_enabled` 控制显隐；Tab 内容各自是独立的路由，
 * 切 Tab 用 `saveState/restoreState` 保留各自的滚动位置。
 *
 * @param onOpen 打开非 Tab 页面（用 [Routes] 的常量）
 */
@Composable
fun MainScreen(onOpen: (String) -> Unit) {
    val viewModel: MainViewModel = hiltViewModel()
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val tabController = rememberNavController()

    LaunchedEffect(state.selected) {
        if (tabController.currentDestination?.route != state.selected.route) {
            tabController.navigate(state.selected.route) {
                popUpTo(tabController.graph.findStartDestination().id) { saveState = true }
                launchSingleTop = true
                restoreState = true
            }
        }
    }

    Column(modifier = Modifier.fillMaxSize()) {
        NavHost(
            navController = tabController,
            startDestination = MainTab.DISCOVER.route,
            modifier = Modifier
                .weight(1f)
                .fillMaxWidth(),
        ) {
            composable(Routes.TAB_DISCOVER) {
                DiscoverRoute(
                    onOpenFilter = { onOpen(Routes.FILTER) },
                    onOpenCoinWallet = { onOpen(Routes.COIN_WALLET) },
                    onOpenIncome = { onOpen(Routes.INCOME) },
                    onOpenUser = { onOpen(Routes.userDetail(it)) },
                    onChat = { onOpen(Routes.chat(it)) },
                )
            }
            composable(Routes.TAB_MATCH) { PlaceholderPage(title = "速配（P02）") }
            composable(Routes.TAB_MESSAGE) {
                MessageRoute(
                    onOpenChat = { onOpen(Routes.chat(it)) },
                    onOpenRoute = onOpen,
                    onOpenDiscoverCity = { viewModel.onDiscoverTabRequested(DiscoverTab.CITY.apiValue) },
                )
            }
            composable(Routes.TAB_MINE) {
                if (BuildConfig.DEBUG) {
                    // P04「我的」还没做（T5.x），但 P14/P08/P09 已经写完却没有入口，
                    // 先在 debug 包放个开发入口页，P04 做完后连 DevEntriesPage 一起删掉
                    DevEntriesPage(
                        title = "我（P04 开发中）",
                        entries = listOf(
                            DevEntry("编辑资料（P14）", ClIcons.edit) { onOpen(Routes.EDIT_PROFILE) },
                            DevEntry("最近访客（P08）", ClIcons.eye) { onOpen(Routes.VISITORS) },
                            DevEntry("喜欢我的人（P09）", ClIcons.heart) { onOpen(Routes.LIKED_ME) },
                            DevEntry("会员中心（P12）", ClIcons.star) { onOpen(Routes.VIP) },
                            DevEntry("每日任务（P11）", ClIcons.calendar) { onOpen(Routes.DAILY_TASK) },
                            DevEntry("我的收益（P26）", ClIcons.wallet) { onOpen(Routes.INCOME) },
                            DevEntry("我的等级与收费（P27）", ClIcons.star) { onOpen(Routes.LEVEL_SETTING) },
                            DevEntry("收益提现（P28）", ClIcons.wallet) { onOpen(Routes.WITHDRAW) },
                            DevEntry("实名认证（P29）", ClIcons.shield) { onOpen(Routes.REAL_NAME) },
                            DevEntry("组件预览页", ClIcons.spark) { onOpen(Routes.GALLERY) },
                        ),
                    )
                } else {
                    PlaceholderPage(title = "我（P04）")
                }
            }
        }

        ClBottomBar(
            items = state.tabs.map { tab ->
                ClBottomBarItem(key = tab.route, label = tab.label, icon = tab.icon)
            },
            selected = state.selected.route,
            badges = state.badges,
            onSelect = viewModel::onTabSelected,
        )
    }
}
