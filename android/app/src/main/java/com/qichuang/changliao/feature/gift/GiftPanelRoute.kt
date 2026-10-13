package com.qichuang.changliao.feature.gift

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.navigation.Routes

/** P23 礼物面板（整页）。从个人详情（P05）进。 */
@Composable
fun GiftPanelRoute(
    userId: Long,
    onBack: () -> Unit,
    onOpenRoute: (String) -> Unit,
    viewModel: GiftPanelViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId) { viewModel.enter(userId) }

    GiftPanelScreen(
        state = state,
        onBack = onBack,
        onSelect = viewModel::onSelect,
        onSend = viewModel::onSend,
        onDismissCoinNotEnough = viewModel::onDismissCoinNotEnough,
        onOpenRecharge = { onOpenRoute(Routes.RECHARGE) },
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
