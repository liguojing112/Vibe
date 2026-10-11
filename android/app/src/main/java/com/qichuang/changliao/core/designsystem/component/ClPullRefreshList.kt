package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 下拉刷新 + 上拉加载更多 + 底部状态（docs/03 第 5.6 节）。
 *
 * - 下拉刷新：[refreshing] / [onRefresh]
 * - 上拉加载：滚到接近底部时自动调 [onLoadMore]
 * - 底部状态：加载中转圈；[loadMoreError] 不为空时显示「加载失败，点击重试」；[hasMore] = false 显示「没有更多了」
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun <T> ClPullRefreshList(
    items: List<T>,
    refreshing: Boolean,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier,
    loadingMore: Boolean = false,
    hasMore: Boolean = true,
    loadMoreError: String? = null,
    onLoadMore: () -> Unit = {},
    contentPadding: PaddingValues = PaddingValues(horizontal = ClTheme.spacing.l),
    key: ((T) -> Any)? = null,
    itemContent: @Composable (T) -> Unit,
) {
    val listState = rememberLazyListState()
    var lastNotifiedIndex by remember { mutableIntStateOf(-1) }

    LaunchedEffect(listState, hasMore, loadingMore, loadMoreError) {
        snapshotFlow { listState.layoutInfo.visibleItemsInfo.lastOrNull()?.index ?: -1 }
            .collect { lastVisible ->
                val threshold = listState.layoutInfo.totalItemsCount - 2
                val canLoad = hasMore && !loadingMore && loadMoreError == null
                if (canLoad && lastVisible >= threshold && lastVisible != lastNotifiedIndex) {
                    lastNotifiedIndex = lastVisible
                    onLoadMore()
                }
            }
    }

    PullToRefreshBox(
        isRefreshing = refreshing,
        onRefresh = onRefresh,
        modifier = modifier.fillMaxSize(),
    ) {
        LazyColumn(
            state = listState,
            modifier = Modifier.fillMaxSize(),
            contentPadding = contentPadding,
        ) {
            items(items = items, key = key) { item ->
                itemContent(item)
            }

            item {
                ClListFooter(
                    loadingMore = loadingMore,
                    hasMore = hasMore,
                    error = loadMoreError,
                    onRetry = onLoadMore,
                )
            }
        }
    }
}

@Composable
private fun ClListFooter(
    loadingMore: Boolean,
    hasMore: Boolean,
    error: String?,
    onRetry: () -> Unit,
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = ClTheme.spacing.xl),
        contentAlignment = Alignment.Center,
    ) {
        when {
            error != null -> ClTextButton(text = "加载失败，点击重试", onClick = onRetry)
            loadingMore -> CircularProgressIndicator(
                modifier = Modifier.size(ClTheme.size.iconS),
                strokeWidth = 2.dp,
                color = ClTheme.colors.primary,
            )
            !hasMore -> Text(
                text = "没有更多了",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
            )
            else -> Unit
        }
    }
}
