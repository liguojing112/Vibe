package com.qichuang.changliao.feature.discover

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.common.MoneyFormat

/**
 * 把 [DiscoverViewModel] 和 [DiscoverScreen] 连起来。
 *
 * `LaunchedEffect(Unit)` 在**每次进入页面**（含从筛选页返回）时跑一遍 `enter()`，
 * 所以筛选改动回到 P01 会立即生效；没改动时它只是一次静默刷新（60 秒内节流）。
 */
@Composable
fun DiscoverRoute(
    onOpenFilter: () -> Unit,
    onOpenCoinWallet: () -> Unit,
    onOpenIncome: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onChat: (Long) -> Unit,
    viewModel: DiscoverViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    DiscoverScreen(
        state = state,
        onTabSelected = viewModel::onTabSelected,
        onOpenFilter = onOpenFilter,
        // 男用户点胶囊进金币钱包（P13），女用户进我的收益（P26）
        onOpenWallet = {
            if (state.balanceUnit == MoneyFormat.UNIT_COIN) onOpenCoinWallet() else onOpenIncome()
        },
        onOpenUser = onOpenUser,
        onChat = { onChat(it.id) },
        onRefresh = viewModel::onRefresh,
        onLoadMore = viewModel::onLoadMore,
        onRetry = viewModel::enter,
    )
}
