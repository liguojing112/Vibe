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
import com.qichuang.changliao.core.designsystem.component.ClBottomBar
import com.qichuang.changliao.core.designsystem.component.ClBottomBarItem
import com.qichuang.changliao.core.navigation.Routes
import com.qichuang.changliao.feature.common.PlaceholderPage
import com.qichuang.changliao.feature.discover.DiscoverRoute
import com.qichuang.changliao.feature.discover.DiscoverTab
import com.qichuang.changliao.feature.message.MessageRoute
import com.qichuang.changliao.feature.mine.MineRoute

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
                // P04 做完了，开发入口页（DevEntriesPage）连同它的 BuildConfig.DEBUG 分支一起删掉
                MineRoute(onOpenRoute = onOpen)
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
