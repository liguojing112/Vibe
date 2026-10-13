package com.qichuang.changliao.feature.wallet

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
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

    WalletScreen(
        state = state,
        onBack = onBack,
        onOpenRecharge = onOpenRecharge,
        onOpenLogs = onOpenLogs,
        onRetry = viewModel::onRetry,
    )
}
