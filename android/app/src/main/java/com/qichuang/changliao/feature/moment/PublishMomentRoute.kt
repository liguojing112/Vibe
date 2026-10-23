package com.qichuang.changliao.feature.moment

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** 发表动态。发布成功后自动关页面。 */
@Composable
fun PublishMomentRoute(
    onBack: () -> Unit,
    viewModel: PublishMomentViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(state.published) {
        if (state.published) {
            onBack()
        }
    }

    PublishMomentScreen(
        state = state,
        onBack = onBack,
        onContentChange = viewModel::onContentChange,
        onImagesPicked = viewModel::onImagesPicked,
        onRemoveImage = viewModel::onRemoveImage,
        onPublish = viewModel::onPublish,
        onToastShown = viewModel::onToastShown,
    )
}
