package com.qichuang.changliao.feature.wallet

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** P24 金币收支明细。 */
@Composable
fun CoinLogsRoute(
    onBack: () -> Unit,
    viewModel: CoinLogsViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    CoinLogsScreen(
        state = state,
        onBack = onBack,
        onTabSelected = viewModel::onTabSelected,
        onRefresh = viewModel::onRefresh,
        onLoadMore = viewModel::onLoadMore,
        onRetry = viewModel::onRetry,
    )
}
