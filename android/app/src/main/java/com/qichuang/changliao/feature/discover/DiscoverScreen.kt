package com.qichuang.changliao.feature.discover

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClCoinPill
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPersonCard
import com.qichuang.changliao.core.designsystem.component.ClPersonCardData
import com.qichuang.changliao.core.designsystem.component.ClPersonTag
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSoftButton
import com.qichuang.changliao.core.designsystem.component.ClSoftButtonKind
import com.qichuang.changliao.core.designsystem.component.ClTagKind
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.designsystem.theme.clTopWashBackground

/**
 * 发现页（docs/01 P01，设计图 01）。
 *
 * 一级页面：顶部渐变 + 大标题 + 余额胶囊 + Tab 行；下面是可下拉刷新、上拉分页的卡片列表。
 * 卡片上的价格、在线/新人/已认证都是服务端给的数据，这里只显示。
 */
@Composable
fun DiscoverScreen(
    state: DiscoverUiState,
    onTabSelected: (DiscoverTab) -> Unit,
    onOpenFilter: () -> Unit,
    onOpenWallet: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onChat: (DiscoverUser) -> Unit,
    onRefresh: () -> Unit,
    onLoadMore: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
            }
        }

        Column(modifier = Modifier.fillMaxSize()) {
            DiscoverHeader(
                state = state,
                onTabSelected = onTabSelected,
                onOpenFilter = onOpenFilter,
                onOpenWallet = onOpenWallet,
            )

            Box(modifier = Modifier.weight(1f)) {
                when {
                    state.loading -> ClLoading()

                    state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                    state.isEmpty -> ClEmpty(
                        title = "这里还没有人",
                        desc = "换个筛选条件再看看",
                        actionText = "去筛选",
                        onAction = onOpenFilter,
                    )

                    else -> ClPullRefreshList(
                        items = state.users,
                        refreshing = state.refreshing,
                        onRefresh = onRefresh,
                        loadingMore = state.loadingMore,
                        hasMore = state.hasMore,
                        loadMoreError = state.loadMoreError,
                        onLoadMore = onLoadMore,
                        contentPadding = PaddingValues(
                            start = ClTheme.spacing.l,
                            end = ClTheme.spacing.l,
                            bottom = ClTheme.spacing.l,
                        ),
                        key = { it.id },
                        header = {
                            ListHeader(
                                filter = state.filter,
                                onOpenFilter = onOpenFilter,
                                onOnlySameCity = { onTabSelected(DiscoverTab.CITY) },
                            )
                        },
                    ) { user ->
                        PersonCard(
                            user = user,
                            onClick = { onOpenUser(user.id) },
                            onChat = { onChat(user) },
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DiscoverHeader(
    state: DiscoverUiState,
    onTabSelected: (DiscoverTab) -> Unit,
    onOpenFilter: () -> Unit,
    onOpenWallet: () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clTopWashBackground()
            .padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.m),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "畅撩交友 ✦",
                    style = ClTheme.typography.pageTitle,
                    color = ClTheme.colors.ink,
                )
                Text(
                    text = "遇见同频的人，聊聊今天的心情",
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.muted,
                )
            }

            state.balanceAmount?.let { amount ->
                ClCoinPill(
                    amount = amount,
                    unit = state.balanceUnit,
                    onClick = onOpenWallet,
                )
            }
        }

        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        ) {
            ClChipGroup(
                options = state.tabs.map { it.label },
                selected = setOf(state.tab.label),
                onSelectionChange = { labels ->
                    state.tabs.firstOrNull { it.label in labels }?.let(onTabSelected)
                },
                modifier = Modifier.weight(1f),
            )
            ClIconButton(
                icon = ClIcons.filter,
                contentDescription = "筛选条件",
                onClick = onOpenFilter,
            )
        }
    }
}

/** 跟着列表一起滚的区块标题（设计图 01 的「现在有空聊」+「只看同城」）。 */
@Composable
private fun ListHeader(
    filter: DiscoverFilter,
    onOpenFilter: () -> Unit,
    onOnlySameCity: () -> Unit,
) {
    Column(modifier = Modifier.fillMaxWidth()) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(vertical = ClTheme.spacing.m),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = if (filter.isActive) "按你的筛选" else "现在有空聊",
                style = ClTheme.typography.sectionTitle,
                color = ClTheme.colors.ink,
                modifier = Modifier.weight(1f),
            )
            if (filter.isActive) {
                ClTextButton(text = "改条件", onClick = onOpenFilter)
            } else {
                ClTextButton(text = "只看同城", onClick = onOnlySameCity)
            }
        }
    }
}

@Composable
private fun PersonCard(
    user: DiscoverUser,
    onClick: () -> Unit,
    onChat: () -> Unit,
) {
    ClPersonCard(
        user = ClPersonCardData(
            name = user.nickname,
            avatarUrl = user.avatar.ifBlank { null },
            online = user.online,
            tags = buildList {
                if (user.verified) {
                    add(ClPersonTag("已认证", ClTagKind.Gold))
                }
                if (user.online) {
                    add(ClPersonTag("在线", ClTagKind.Green))
                }
                if (user.isNew) {
                    add(ClPersonTag("新人", ClTagKind.Pink))
                }
            },
            meta = metaOf(user),
            intro = user.bio.ifBlank { null },
        ),
        onClick = onClick,
        actions = {
            ClSoftButton(
                text = chatLabel(user),
                kind = ClSoftButtonKind.Gray,
                leadingIcon = ClIcons.message,
                onClick = onChat,
            )
        },
    )
}

/** 「26 岁 · 上海 · 品牌设计师」，缺哪项就不显示哪项。 */
private fun metaOf(user: DiscoverUser): String = listOfNotNull(
    user.age?.let { "$it 岁" },
    user.city.ifBlank { null },
    user.job.ifBlank { null },
).joinToString(" · ")

/**
 * 私信按钮文案：**男用户看女用户才带价格**（价格是服务端按女方等级算好的），女用户只显示「私信」。
 */
private fun chatLabel(user: DiscoverUser): String {
    val price = user.messagePrice ?: return "私信"

    return "私信 " + MoneyFormat.coinPrice(price, MoneyFormat.UNIT_PER_MESSAGE)
}
