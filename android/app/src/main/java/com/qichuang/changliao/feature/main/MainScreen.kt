package com.qichuang.changliao.feature.main

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.activity.compose.BackHandler
import androidx.activity.compose.LocalActivity
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.designsystem.component.ClBottomBar
import com.qichuang.changliao.core.designsystem.component.ClBottomBarItem
import com.qichuang.changliao.core.navigation.Routes
import com.qichuang.changliao.feature.discover.DiscoverRoute
import com.qichuang.changliao.feature.discover.DiscoverTab
import com.qichuang.changliao.feature.match.MatchRoute
import com.qichuang.changliao.feature.message.MessageRoute
import com.qichuang.changliao.feature.mine.MineRoute

/**
 * 主界面：底部导航 + Tab 内容（docs/01 第 3 节）。
 *
 * 「速配」Tab 由 `feature.call_enabled` 控制显隐；Tab 内容各自是独立的路由，
 * 切 Tab 用 `saveState/restoreState` 保留各自的滚动位置。
 *
 * P16 语音大厅也挂在这棵 Tab 导航树里（不在底部导航上）—— 这样它和 P02 一样
 * **底部导航栏一直在**（设计图 16 就是这样），「速配」保持高亮。
 *
 * @param onOpen 打开非 Tab 页面（用 [Routes] 的常量）
 */
@Composable
fun MainScreen(onOpen: (String) -> Unit) {
    val viewModel: MainViewModel = hiltViewModel()
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val tabController = rememberNavController()
    val currentTab by tabController.currentBackStackEntryAsState()
    val currentRoute = currentTab?.destination?.route

    /** 切到某个 Tab（切 Tab 和「从 P16 点回速配」都走这里）。 */
    fun goTab(route: String) {
        tabController.navigate(route) {
            popUpTo(tabController.graph.findStartDestination().id) { saveState = true }
            launchSingleTop = true
            restoreState = true
        }
    }

    LaunchedEffect(state.selected) {
        if (tabController.currentDestination?.route != state.selected.route) {
            goTab(state.selected.route)
        }
    }

    // 底部导航高亮的**唯一真相来源是当前路由**，不是 `state.selected`。
    // `state.selected` 是 ViewModel 里那个「点 Tab 切过去」的状态；而从 P01 的快捷卡直接
    // `goTab(...)` 进 P02/P16 时它并不会变（也没必要变）—— 只用它的话会看到
    // 「页面已经跳到速配、底栏还高亮着遇见」（P01 快捷卡跳转时就出现过）。
    // P16 不在 items 里，但要跟着高亮「速配」，所以先把两个速配页折叠成 MATCH。
    val barSelected = when (currentRoute) {
        Routes.VOICE_HALL -> Routes.TAB_MATCH
        else -> currentRoute ?: state.selected.route
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
                    onCall = { userId, kind -> onOpen(Routes.callConfirm(userId, kind)) },
                    // 两张快捷卡进 P16 / P02 —— 它们是 Tab 级的页面，走 Tab 导航树才有底部导航
                    onOpenVoiceHall = { goTab(Routes.VOICE_HALL) },
                    onOpenVideoMatch = { goTab(Routes.TAB_MATCH) },
                )
            }
            composable(Routes.TAB_MATCH) {
                MatchRoute(
                    kind = CallKind.VIDEO,
                    onSwitchKind = { kind ->
                        goTab(if (kind == CallKind.VOICE) Routes.VOICE_HALL else Routes.TAB_MATCH)
                    },
                    onOpenFilter = { onOpen(Routes.FILTER) },
                    onOpenWallet = { onOpen(Routes.COIN_WALLET) },
                    onOpenUser = { onOpen(Routes.userDetail(it)) },
                    onCall = { onOpen(Routes.callConfirm(it, CallKind.VIDEO)) },
                )
            }
            composable(Routes.VOICE_HALL) {
                MatchRoute(
                    kind = CallKind.VOICE,
                    onSwitchKind = { kind ->
                        goTab(if (kind == CallKind.VOICE) Routes.VOICE_HALL else Routes.TAB_MATCH)
                    },
                    onOpenFilter = { onOpen(Routes.FILTER) },
                    onOpenWallet = { onOpen(Routes.COIN_WALLET) },
                    onOpenUser = { onOpen(Routes.userDetail(it)) },
                    onCall = { onOpen(Routes.callConfirm(it, CallKind.VOICE)) },
                )
            }
            composable(Routes.TAB_MESSAGE) {
                MessageRoute(
                    onOpenChat = { onOpen(Routes.chat(it)) },
                    onOpenRoute = onOpen,
                    onOpenDiscover = { goTab(Routes.TAB_DISCOVER) },
                    onOpenDiscoverCity = { viewModel.onDiscoverTabRequested(DiscoverTab.CITY.apiValue) },
                )
            }
            composable(Routes.TAB_MINE) {
                MineRoute(onOpenRoute = onOpen)
            }
        }

        ClBottomBar(
            items = state.tabs.map { tab ->
                ClBottomBarItem(key = tab.route, label = tab.label, icon = tab.icon)
            },
            selected = barSelected,
            badges = state.badges,
            onSelect = { route ->
                viewModel.onTabSelected(route)
                // 停在 P16 时点「速配」：selected 本来就是 MATCH，上面的 LaunchedEffect 不会触发，
                // 所以这里直接导航一次（currentRoute != route 才不会白跳）
                if (currentRoute != route) {
                    goTab(route)
                }
            },
        )

        // 放在 NavHost **之后**：OnBackPressedDispatcher 后注册的先回调，写在前面会被
        // NavHost 自己的返回处理抢先（Tab 栈里 pop 回上一个 Tab）。
        val activity = LocalActivity.current
        BackHandler(enabled = currentRoute != null && currentRoute != Routes.TAB_DISCOVER) {
            activity?.moveTaskToBack(true)
        }
    }
}
