package com.qichuang.changliao.feature.moment

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.LifecycleResumeEffect
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.Moment

/**
 * 朋友圈列表（P05 的「查看全部」/「我的朋友圈」）。
 *
 * @param userId 0 = 我的朋友圈
 */
@Composable
fun MomentListRoute(
    userId: Long,
    onBack: () -> Unit,
    onPublish: () -> Unit,
    viewModel: MomentViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    var commentsFor by remember { mutableStateOf<Moment?>(null) }
    var firstResume by remember { mutableStateOf(true) }

    LaunchedEffect(userId) { viewModel.enter(userId) }

    // 从发布页 / 详情页回来时重新拉一次：不然刚发的动态不会出现在列表里
    LifecycleResumeEffect(Unit) {
        if (firstResume) {
            firstResume = false
        } else {
            viewModel.onRefresh()
        }
        onPauseOrDispose { }
    }

    MomentListScreen(
        state = state,
        onBack = onBack,
        onPublish = onPublish,
        onRefresh = viewModel::onRefresh,
        onLoadMore = viewModel::onLoadMore,
        onRetry = viewModel::onRetry,
        onToggleLike = viewModel::onToggleLike,
        onComment = { commentsFor = it },
        onDelete = viewModel::onDelete,
        onPreview = viewModel::onPreview,
        onClosePreview = viewModel::onClosePreview,
        onToastShown = viewModel::onToastShown,
    )

    commentsFor?.let { moment ->
        MomentCommentsSheet(
            momentId = moment.id,
            onDismiss = { commentsFor = null },
            onCountChanged = { count -> viewModel.onCommentsCountChanged(moment.id, count) },
        )
    }
}
