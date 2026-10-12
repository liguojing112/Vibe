package com.qichuang.changliao.feature.relation

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.data.model.RelationKind
import com.qichuang.changliao.core.data.model.RelationPerson
import com.qichuang.changliao.core.designsystem.component.ClConfirmDialog
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClMiniProfileRow
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 「最近访客」（P08）和「喜欢我的人」（P09）共用的页面。
 *
 * 非会员拿到的是打码数据（昵称只留首字、头像要糊、`locked = true`），
 * 这时点任何一行都引导去开通会员，而不是进对方主页。
 */
@Composable
fun RelationListScreen(
    state: RelationListUiState,
    onBack: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onOpenVip: () -> Unit,
    onFollowBack: (RelationPerson) -> Unit,
    onVipPromptVisibleChange: (Boolean) -> Unit,
    onRefresh: () -> Unit,
    onLoadMore: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = state.title, onBack = onBack) },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
            }
        }

        Box(modifier = Modifier.weight(1f)) {
            when {
                state.loading -> ClLoading()

                state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                state.isEmpty -> ClEmpty(
                    title = if (state.kind == RelationKind.VISITORS) "还没有人来看过" else "还没有人喜欢你",
                    desc = "完善资料、多发动态，更容易被注意到",
                )

                else -> ClPullRefreshList(
                    items = state.people,
                    refreshing = state.refreshing,
                    onRefresh = onRefresh,
                    loadingMore = state.loadingMore,
                    hasMore = state.hasMore,
                    loadMoreError = state.loadMoreError,
                    onLoadMore = onLoadMore,
                    key = { it.id },
                    header = {
                        state.noticeTitle?.let { title ->
                            ClNoticeCard(
                                title = title,
                                content = state.noticeContent,
                                highlight = true,
                                modifier = Modifier.padding(
                                    top = ClTheme.spacing.m,
                                    bottom = ClTheme.spacing.s,
                                ),
                            )
                        }
                    },
                ) { person ->
                    RelationRow(
                        kind = state.kind,
                        person = person,
                        locked = state.locked,
                        onOpenUser = onOpenUser,
                        onOpenVip = { onVipPromptVisibleChange(true) },
                        onFollowBack = onFollowBack,
                    )
                }
            }
        }

        if (state.vipPromptVisible) {
            ClConfirmDialog(
                title = "开通会员查看全部",
                message = "开通会员后可以看到完整的昵称和头像。",
                confirmText = "开通会员",
                onConfirm = {
                    onVipPromptVisibleChange(false)
                    onOpenVip()
                },
                onDismiss = { onVipPromptVisibleChange(false) },
            )
        }
    }
}

@Composable
private fun RelationRow(
    kind: RelationKind,
    person: RelationPerson,
    locked: Boolean,
    onOpenUser: (Long) -> Unit,
    onOpenVip: () -> Unit,
    onFollowBack: (RelationPerson) -> Unit,
) {
    ClMiniProfileRow(
        name = person.nickname,
        avatarUrl = person.avatar.ifBlank { null },
        desc = relationDesc(kind, person),
        blurred = locked,
        buttonText = relationActionLabel(kind, person),
        // 打码时不进对方主页，改为引导开通会员
        onClick = { if (locked) onOpenVip() else onOpenUser(person.id) },
        onAction = {
            when {
                locked -> onOpenVip()
                // P09 没互关时的按钮是「回关」
                kind == RelationKind.FANS && !person.isMutual -> onFollowBack(person)
                else -> onOpenUser(person.id)
            }
        },
        modifier = Modifier.fillMaxWidth(),
    )
}
