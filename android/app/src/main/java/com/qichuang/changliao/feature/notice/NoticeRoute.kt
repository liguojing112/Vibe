package com.qichuang.changliao.feature.notice

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.NoticeCategory

/**
 * P10 系统消息。入口在 P03 消息页的「系统消息」（落「系统通知」）和「互动通知」（落「互动」），
 * 也在 P04「我的」的菜单里。
 *
 * @param initialCategory 进来时停在哪个分类
 */
@Composable
fun NoticeRoute(
    onBack: () -> Unit,
    initialCategory: NoticeCategory = NoticeCategory.SYSTEM,
    viewModel: NoticeViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(Unit) { viewModel.enter(initialCategory) }

    NoticeScreen(
        state = state,
        onBack = onBack,
        onCategoryChange = viewModel::onCategoryChange,
        onRefresh = viewModel::onRefresh,
        onLoadMore = viewModel::onLoadMore,
        onRetry = viewModel::onRetry,
    )
}
