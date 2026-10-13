package com.qichuang.changliao.feature.notice

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.Notice
import com.qichuang.changliao.core.data.model.NoticeCategory
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 系统消息（P10，设计图 10）。
 *
 * 两个 Tab：「系统通知」（后台公告 + 发给我的通知：资料审核、提现、实名、封号）
 * 和「互动」（谁关注了我、谁送了我礼物）。
 *
 * **进入即标为已读**（`docs/01` P10）—— 这件事服务端在返回列表时就做了，页面不用再调一次；
 * 返回的 `read` 是「打开这一趟之前读没读过」，用它把标题分成深/浅两种颜色
 * （`docs/01` P10 没要求小圆点，就不多做一个组件了）。
 */
@Composable
fun NoticeScreen(
    state: NoticeUiState,
    onBack: () -> Unit,
    onCategoryChange: (NoticeCategory) -> Unit,
    onRefresh: () -> Unit,
    onLoadMore: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "系统消息", onBack = onBack) },
    ) {
        // Tab **不能放进列表的 header 里**：列表空的时候 header 也没了，用户就切不回另一个分类
        ClChipGroup(
            options = NoticeCategory.entries.map { it.label },
            selected = setOf(state.category.label),
            onSelectionChange = { labels ->
                NoticeCategory.entries.firstOrNull { it.label in labels }?.let(onCategoryChange)
            },
            modifier = Modifier.padding(
                start = ClTheme.spacing.l,
                end = ClTheme.spacing.l,
                top = ClTheme.spacing.l,
                bottom = ClTheme.spacing.m,
            ),
        )

        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            state.isEmpty -> ClEmpty(
                title = "暂时没有消息",
                desc = when (state.category) {
                    NoticeCategory.SYSTEM -> "平台公告和审核结果会出现在这里"
                    NoticeCategory.INTERACT -> "有人关注你或送你礼物时会出现在这里"
                },
            )

            else -> ClPullRefreshList(
                items = state.notices,
                refreshing = state.refreshing,
                onRefresh = onRefresh,
                loadingMore = state.loadingMore,
                hasMore = state.hasMore,
                onLoadMore = onLoadMore,
                key = { it.id },
                itemContent = { notice ->
                    NoticeCard(notice = notice, modifier = Modifier.padding(bottom = ClTheme.spacing.m))
                },
            )
        }
    }
}

/** 一条通知卡片：标题 + 内容 + 时间。未读的标题用深色、已读用浅色。 */
@Composable
private fun NoticeCard(notice: Notice, modifier: Modifier = Modifier) {
    ClCard(modifier = modifier) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        ) {
            Text(
                text = notice.title,
                style = ClTheme.typography.cardTitle,
                color = if (notice.read) ClTheme.colors.inkSecondary else ClTheme.colors.ink,
                modifier = Modifier.weight(1f),
            )
            Text(
                text = TimeFormat.friendly(notice.createdAt / 1000),
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.muted,
            )
        }

        if (notice.content.isNotBlank()) {
            Text(
                text = notice.content,
                style = ClTheme.typography.bodySmall,
                color = ClTheme.colors.inkSecondary,
            )
        }
    }
}
