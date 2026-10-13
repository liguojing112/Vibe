package com.qichuang.changliao.feature.withdraw

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P28 收益提现。从 P26 的「申请提现」进来，也可以从「我」页面的钱包卡进来。
 */
@Composable
fun WithdrawRoute(
    onBack: () -> Unit,
    onOpenRealName: () -> Unit,
    viewModel: WithdrawViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    WithdrawScreen(
        state = state,
        onBack = onBack,
        onSelectTier = viewModel::onSelectTier,
        onAccountTypeChange = viewModel::onAccountTypeChange,
        onAccountNoChange = viewModel::onAccountNoChange,
        onBankNameChange = viewModel::onBankNameChange,
        onSubmit = viewModel::onSubmit,
        onOpenRealName = onOpenRealName,
        onLoadMore = viewModel::onLoadMoreRecords,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
