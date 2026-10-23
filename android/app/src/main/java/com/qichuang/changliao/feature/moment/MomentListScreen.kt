package com.qichuang.changliao.feature.moment

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.Moment
import com.qichuang.changliao.core.designsystem.component.ClConfirmDialog
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClImagePreview
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.feature.moment.components.MomentCard

/**
 * 朋友圈列表：某人（或我自己）的动态，下拉刷新 + 上拉加载。
 *
 * 评论走底部弹窗（[MomentCommentsSheet]），不放独立页面 —— 看完就能回列表，
 * 不用多按一次返回。
 */
@Composable
fun MomentListScreen(
    state: MomentUiState,
    onBack: () -> Unit,
    onPublish: () -> Unit,
    onRefresh: () -> Unit,
    onLoadMore: () -> Unit,
    onRetry: () -> Unit,
    onToggleLike: (Moment) -> Unit,
    onComment: (Moment) -> Unit,
    onDelete: (Moment) -> Unit,
    onPreview: (List<String>, Int) -> Unit,
    onClosePreview: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val toast = rememberClToast()
    var pendingDelete by remember { mutableStateOf<Moment?>(null) }

    LaunchedEffect(state.toast) {
        state.toast?.let {
            toast.show(it)
            onToastShown()
        }
    }

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = {
            ClTopBar(
                title = state.title,
                onBack = onBack,
                action = {
                    if (state.isMine) {
                        ClIconButton(
                            icon = ClIcons.add,
                            contentDescription = "发布动态",
                            onClick = onPublish,
                        )
                    }
                },
            )
        },
    ) {
        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            state.isEmpty -> ClEmpty(
                title = "还没有动态",
                desc = if (state.isMine) "发一条记录今天的心情吧" else "这个人还没发过朋友圈",
                actionText = if (state.isMine) "发一条" else null,
                onAction = if (state.isMine) onPublish else null,
            )

            else -> ClPullRefreshList(
                items = state.moments,
                refreshing = state.refreshing,
                onRefresh = onRefresh,
                loadingMore = state.loadingMore,
                hasMore = state.hasMore,
                onLoadMore = onLoadMore,
                key = { it.id },
                itemContent = { moment ->
                    MomentCard(
                        moment = moment,
                        onToggleLike = { onToggleLike(moment) },
                        onComment = { onComment(moment) },
                        onDelete = { pendingDelete = moment },
                        onPreview = { index -> onPreview(moment.images, index) },
                    )
                },
            )
        }
    }

    val previewIndex = state.previewIndex
    if (previewIndex != null && state.previewUrls.isNotEmpty()) {
        ClImagePreview(
            urls = state.previewUrls,
            initialIndex = previewIndex,
            onDismiss = onClosePreview,
        )
    }

    pendingDelete?.let { moment ->
        ClConfirmDialog(
            title = "删除这条动态？",
            message = "删除后不会出现在列表里，别人也看不到了。",
            confirmText = "删除",
            danger = true,
            onConfirm = {
                onDelete(moment)
                pendingDelete = null
            },
            onDismiss = { pendingDelete = null },
        )
    }
}
