package com.qichuang.changliao.feature.moment

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.MomentComment
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 评论弹窗：上面是评论列表，下面固定输入框。
 *
 * 高度固定占屏幕一部分（`fillMaxHeight(0.6f)`），不然评论多了会把输入框顶到屏幕外。
 */
@Composable
fun MomentCommentsSheet(
    momentId: Long,
    onDismiss: () -> Unit,
    onCountChanged: (Int) -> Unit,
    viewModel: MomentCommentsViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()
    val toast = rememberClToast()

    LaunchedEffect(momentId) { viewModel.load(momentId) }

    LaunchedEffect(state.total) {
        if (state.momentId == momentId) {
            onCountChanged(state.total)
        }
    }

    LaunchedEffect(state.toast) {
        state.toast?.let {
            toast.show(it)
            viewModel.onToastShown()
        }
    }

    ClBottomSheet(title = "评论", onClose = onDismiss) {
        Column(modifier = Modifier.fillMaxHeight(0.6f)) {
            Box(modifier = Modifier.weight(1f)) {
                when {
                    state.loading -> ClLoading()

                    state.isEmpty -> ClEmpty(title = "还没有评论", desc = "说点什么吧")

                    else -> ClPullRefreshList(
                        items = state.comments,
                        refreshing = false,
                        onRefresh = viewModel::onRefresh,
                        loadingMore = state.loadingMore,
                        hasMore = state.hasMore,
                        onLoadMore = viewModel::onLoadMore,
                        key = { it.id },
                        itemContent = { comment -> CommentRow(comment) },
                    )
                }
            }

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
            ) {
                ClTextField(
                    label = "评论",
                    value = state.input,
                    onValueChange = viewModel::onInputChange,
                    placeholder = "说点什么…",
                    modifier = Modifier.weight(1f),
                )
                ClPrimaryButton(
                    text = "发送",
                    fillMaxWidth = false,
                    enabled = state.canSend,
                    loading = state.sending,
                    onClick = viewModel::onSend,
                )
            }
        }
    }
}

@Composable
private fun CommentRow(comment: MomentComment) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.s),
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
    ) {
        ClAvatar(
            url = comment.avatar,
            size = ClTheme.size.avatarXS,
            contentDescription = comment.nickname,
        )
        Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs)) {
            Text(
                text = comment.nickname,
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.inkSecondary,
            )
            Text(
                text = comment.content,
                style = ClTheme.typography.body,
                color = ClTheme.colors.ink,
            )
        }
    }
}
