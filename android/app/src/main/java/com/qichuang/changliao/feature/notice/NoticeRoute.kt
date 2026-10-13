package com.qichuang.changliao.feature.notice

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/**
 * P10 系统消息。入口在 P03 消息页的「系统消息」和 P04「我的」的菜单里。
 */
@Composable
fun NoticeRoute(
    onBack: () -> Unit,
    viewModel: NoticeViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter() }

    NoticeScreen(
        state = state,
        onBack = onBack,
        onCategoryChange = viewModel::onCategoryChange,
        onRefresh = viewModel::onRefresh,
        onLoadMore = viewModel::onLoadMore,
        onRetry = viewModel::onRetry,
    )
}
