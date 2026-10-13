package com.qichuang.changliao.feature.income

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P26 我的收益。入口在「我」页面的钱包卡（P04）。
 */
@Composable
fun IncomeRoute(
    onBack: () -> Unit,
    onOpenWithdraw: () -> Unit,
    onOpenLevel: () -> Unit,
    viewModel: IncomeViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    IncomeScreen(
        state = state,
        onBack = onBack,
        onOpenWithdraw = onOpenWithdraw,
        onOpenLevel = onOpenLevel,
        onLoadMore = viewModel::onLoadMoreLogs,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
