package com.qichuang.changliao.feature.income

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P27 我的等级与收费。从 P26 的「我的等级」进来，也可以从「我」页面的菜单进来。
 */
@Composable
fun IncomeLevelRoute(
    onBack: () -> Unit,
    onOpenRealName: () -> Unit,
    viewModel: IncomeLevelViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    IncomeLevelScreen(
        state = state,
        onBack = onBack,
        onOpenRealName = onOpenRealName,
        onToggleOnline = viewModel::onToggleOnline,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
