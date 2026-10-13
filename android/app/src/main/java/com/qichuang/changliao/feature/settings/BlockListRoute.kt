package com.qichuang.changliao.feature.settings

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** P15 里的黑名单列表。 */
@Composable
fun BlockListRoute(
    onBack: () -> Unit,
    viewModel: BlockListViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    BlockListScreen(
        state = state,
        onBack = onBack,
        onRemove = viewModel::onRemove,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
