package com.qichuang.changliao.feature.discover

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * 注册后的偏好引导：复用筛选页，只是换个标题、去掉返回箭头、加「跳过」。
 *
 * 保存和跳过都直接进主界面 —— 引导不该成为注册的卡点。
 */
@Composable
fun PreferenceRoute(
    onDone: () -> Unit,
    viewModel: FilterViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.load() }

    LaunchedEffect(state.applied) {
        if (state.applied) {
            onDone()
        }
    }

    FilterScreen(
        state = state,
        onCitySelected = viewModel::onCitySelected,
        onAgeRangeChange = viewModel::onAgeRangeChange,
        onPurposesChange = viewModel::onPurposesChange,
        onVerificationsChange = viewModel::onVerificationsChange,
        onOnlineOnlyChange = viewModel::onOnlineOnlyChange,
        onReset = viewModel::onReset,
        onApply = viewModel::onApply,
        onRetry = viewModel::load,
        onBack = onDone,
        onboarding = true,
        onSkip = onDone,
    )
}
