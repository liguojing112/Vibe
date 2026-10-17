package com.qichuang.changliao.feature.wallet

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.LifecycleResumeEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** P13 金币钱包。 */
@Composable
fun WalletRoute(
    onBack: () -> Unit,
    onOpenRecharge: () -> Unit,
    onOpenLogs: () -> Unit,
    viewModel: WalletViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    // 从充值页返回时余额要变（T9.7）
    LifecycleResumeEffect(Unit) {
        viewModel.refresh()
        onPauseOrDispose { }
    }

    WalletScreen(
        state = state,
        onBack = onBack,
        onOpenRecharge = onOpenRecharge,
        onOpenLogs = onOpenLogs,
        onRetry = viewModel::onRetry,
    )
}
