package com.qichuang.changliao.feature.realname

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P29 实名认证。入口在「我」页面的菜单（P04）和 P27/P28 的引导。
 */
@Composable
fun RealNameRoute(
    onBack: () -> Unit,
    viewModel: RealNameViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    RealNameScreen(
        state = state,
        onBack = onBack,
        onNameChange = viewModel::onNameChange,
        onIdCardChange = viewModel::onIdCardChange,
        onSubmit = viewModel::onSubmit,
        onToastShown = viewModel::onToastShown,
        onRetry = viewModel::onRetry,
    )
}
