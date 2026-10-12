package com.qichuang.changliao.feature.relation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.RelationKind

/** P08 最近访客。 */
@Composable
fun VisitorsRoute(
    onBack: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onOpenVip: () -> Unit,
    viewModel: RelationListViewModel = hiltViewModel(),
) {
    RelationListRoute(RelationKind.VISITORS, onBack, onOpenUser, onOpenVip, viewModel)
}

/** P09 喜欢我的人。 */
@Composable
fun FansRoute(
    onBack: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onOpenVip: () -> Unit,
    viewModel: RelationListViewModel = hiltViewModel(),
) {
    RelationListRoute(RelationKind.FANS, onBack, onOpenUser, onOpenVip, viewModel)
}

@Composable
private fun RelationListRoute(
    kind: RelationKind,
    onBack: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onOpenVip: () -> Unit,
    viewModel: RelationListViewModel,
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(kind) { viewModel.enter(kind) }

    RelationListScreen(
        state = state,
        onBack = onBack,
        onOpenUser = onOpenUser,
        onOpenVip = onOpenVip,
        onFollowBack = viewModel::onFollowBack,
        onVipPromptVisibleChange = viewModel::onVipPromptVisibleChange,
        onRefresh = viewModel::onRefresh,
        onLoadMore = viewModel::onLoadMore,
        onRetry = { viewModel.enter(kind) },
    )
}
