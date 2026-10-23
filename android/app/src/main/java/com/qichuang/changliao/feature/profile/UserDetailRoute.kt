package com.qichuang.changliao.feature.profile

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** 把 [UserDetailViewModel] 和 [UserDetailScreen] 连起来。 */
@Composable
fun UserDetailRoute(
    userId: Long,
    onBack: () -> Unit,
    onChat: (Long) -> Unit,
    onOpenGift: (Long, String) -> Unit,
    onOpenMoments: (Long) -> Unit,
    viewModel: UserDetailViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(userId) { viewModel.load(userId) }

    // 拉黑成功后自动回上一页：拉黑后互相不可见，停在这页没意义
    LaunchedEffect(state.blocked) {
        if (state.blocked) {
            onBack()
        }
    }

    val actions = remember(viewModel, userId, onChat, onOpenGift, onOpenMoments, state.detail?.nickname) {
        UserDetailActions(
            onChat = { onChat(userId) },
            onOpenGift = { onOpenGift(userId, state.detail?.nickname.orEmpty()) },
            onOpenMoments = { onOpenMoments(userId) },
            onToggleFollow = viewModel::onToggleFollow,
            onMenuVisibleChange = viewModel::onMenuVisibleChange,
            onBlockConfirmVisibleChange = viewModel::onBlockConfirmVisibleChange,
            onConfirmBlock = viewModel::onConfirmBlock,
            onReportVisibleChange = viewModel::onReportVisibleChange,
            onReportReasonChange = viewModel::onReportReasonChange,
            onReportContentChange = viewModel::onReportContentChange,
            onSubmitReport = viewModel::onSubmitReport,
            onPreview = viewModel::onPreview,
            onRetry = { viewModel.load(userId) },
        )
    }

    UserDetailScreen(state = state, actions = actions, onBack = onBack)
}
