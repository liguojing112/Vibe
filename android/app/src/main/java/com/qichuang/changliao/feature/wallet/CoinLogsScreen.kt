package com.qichuang.changliao.feature.wallet

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.CoinLogEntry
import com.qichuang.changliao.core.data.model.CoinLogTab
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPullRefreshList
import com.qichuang.changliao.core.designsystem.component.ClRecordRow
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 金币收支明细（P24，设计图 24）。
 *
 * Tab（全部/充值/消费）+ 列表（图标、标题、时间、金额、变动后余额）+ 分页。
 * 标题是服务端在写流水时就拼好的（「私信 · 小满」「礼物 · 浪漫花束」），页面不拼文案。
 */
@Composable
fun CoinLogsScreen(
    state: CoinLogsUiState,
    onBack: () -> Unit,
    onTabSelected: (CoinLogTab) -> Unit,
    onRefresh: () -> Unit,
    onLoadMore: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "金币收支明细", onBack = onBack) },
    ) {
        Column(modifier = Modifier.fillMaxSize()) {
            ClChipGroup(
                options = CoinLogTab.entries.map { it.label },
                selected = setOf(state.tab.label),
                onSelectionChange = { selection ->
                    CoinLogTab.entries.firstOrNull { it.label in selection }?.let(onTabSelected)
                },
                modifier = Modifier.padding(horizontal = ClTheme.spacing.l, vertical = ClTheme.spacing.s),
            )

            Box(modifier = Modifier.weight(1f)) {
                when {
                    state.loading -> ClLoading()

                    state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                    state.isEmpty -> ClEmpty(
                        title = state.emptyTitle,
                        desc = "充值或消费之后会在这里留一条记录",
                    )

                    else -> ClPullRefreshList(
                        items = state.entries,
                        refreshing = state.refreshing,
                        onRefresh = onRefresh,
                        loadingMore = state.loadingMore,
                        hasMore = state.hasMore,
                        loadMoreError = state.loadMoreError,
                        onLoadMore = onLoadMore,
                        key = { it.id },
                        header = { BalanceLine(state.balance) },
                    ) { entry ->
                        ClRecordRow(
                            icon = entry.icon(),
                            title = entry.title,
                            time = entry.subtitle(),
                            amount = MoneyFormat.signed(entry.change),
                            positive = entry.isIncome,
                            showDivider = false,
                        )
                    }
                }
            }
        }
    }
}

/** 「当前余额 240 金币」——设计图 24 在 Tab 下面挂了这一行。 */
@Composable
private fun BalanceLine(balance: Long) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = ClTheme.spacing.s),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xxs),
    ) {
        Text(
            text = "当前余额 ${MoneyFormat.coins(balance)}",
            style = ClTheme.typography.bodySmall,
            color = ClTheme.colors.inkSecondary,
        )
    }
}

/** 流水按类型挑图标（图标只能用 `ClIcons` 里有的那些）。 */
private fun CoinLogEntry.icon(): ImageVector = when (type) {
    "RECHARGE" -> ClIcons.wallet
    "MESSAGE" -> ClIcons.message
    "GIFT" -> ClIcons.gift
    "VIP" -> ClIcons.star
    "TASK" -> ClIcons.spark
    "ADMIN_DEDUCT" -> ClIcons.warning
    else -> ClIcons.more
}

/**
 * 副标题：`今天 17:55 · 消费 · 余额 240 币`。
 *
 * 时间用 `TimeFormat.friendly`（今天 HH:mm / 昨天 HH:mm / MM-dd HH:mm，docs/03 第 6 节第 5 条）；
 * 「变动后余额」是 docs/01 P24 明确要求的字段。
 */
private fun CoinLogEntry.subtitle(): String {
    val time = TimeFormat.friendly(createdAt / 1000)
    val kind = if (isIncome) "收入" else "消费"

    return listOf(time, kind, "余额 ${MoneyFormat.coinsShort(balanceAfter)}")
        .filter { it.isNotBlank() }
        .joinToString(" · ")
}
