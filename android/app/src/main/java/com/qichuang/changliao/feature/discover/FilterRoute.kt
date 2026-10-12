package com.qichuang.changliao.feature.discover

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** 把 [FilterViewModel] 和 [FilterScreen] 连起来。保存成功后自动返回上一页。 */
@Composable
fun FilterRoute(
    onBack: () -> Unit,
    viewModel: FilterViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.load() }

    LaunchedEffect(state.applied) {
        if (state.applied) {
            onBack()
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
        onBack = onBack,
    )
}
