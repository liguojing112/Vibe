package com.qichuang.changliao.feature.match

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.designsystem.component.ClChipGroup
import com.qichuang.changliao.core.designsystem.component.ClCoinPill
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClIconButton
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClMatchCard
import com.qichuang.changliao.core.designsystem.component.ClMatchCardData
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.designsystem.theme.clTopWashBackground

/** 两个「在线」Tab 的名字（P02 和 P16 的 chips 是同一对）。 */
private const val TAB_VIDEO = "视频在线"
private const val TAB_VOICE = "语音在线"

/**
 * P02 视频速配（docs/01 P02，设计图 02）：两列卡片网格，卡片上是「可视频」角标 + 视频单价。
 *
 * 数据来自 `/discover` 的 `video` Tab（服务端只给「现在能接」的人）。
 * 点「视频聊」→ P17；点卡片 → P05 个人详情。
 */
@Composable
fun MatchScreen(
    state: MatchUiState,
    onSwitchKind: (CallKind) -> Unit,
    onOpenFilter: () -> Unit,
    onOpenWallet: () -> Unit,
    onOpenUser: (Long) -> Unit,
    onCall: (Long) -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier, containerColor = ClTheme.colors.bgPage) {
        Column(modifier = Modifier.fillMaxSize()) {
            MatchHeader(
                state = state,
                title = "视频速配",
                subtitle = "先看看资料，再和喜欢的人面对面",
                hint = exampleHint(state.priceMin),
                onSwitchKind = onSwitchKind,
                onOpenFilter = onOpenFilter,
                onOpenWallet = onOpenWallet,
            )

            when {
                state.loading -> ClLoading()

                state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                state.isEmpty -> ClEmpty(
                    title = "现在没人视频在线",
                    desc = "过一会儿再来看看",
                    actionText = "刷新",
                    onAction = onRetry,
                )

                else -> LazyVerticalGrid(
                    columns = GridCells.Fixed(2),
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        bottom = ClTheme.spacing.l,
                    ),
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                ) {
                    items(state.users, key = { it.id }) { user ->
                        ClMatchCard(
                            user = ClMatchCardData(
                                name = user.nickname,
                                photoUrl = user.avatar.ifBlank { null },
                                meta = metaOf(user),
                            ),
                            // 开关打开才进得来，所以这里一定显示「可视频」；服务端没给单价就不写价格
                            actionText = callActionText("视频聊", user.videoPrice),
                            onAction = { onCall(user.id) },
                            onClick = { onOpenUser(user.id) },
                        )
                    }
                }
            }
        }
    }
}

/** P02 的页头（P16 用同一个，只是文案和按钮不同）。 */
@Composable
internal fun MatchHeader(
    state: MatchUiState,
    title: String,
    subtitle: String,
    hint: String,
    onSwitchKind: (CallKind) -> Unit,
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
                Text(text = title, style = ClTheme.typography.pageTitle, color = ClTheme.colors.ink)
                Text(text = subtitle, style = ClTheme.typography.caption, color = ClTheme.colors.muted)
            }
            state.balanceAmount?.let { amount ->
                ClCoinPill(amount = amount, unit = state.balanceUnit, onClick = onOpenWallet)
            }
        }

        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        ) {
            ClChipGroup(
                options = listOf(TAB_VIDEO, TAB_VOICE),
                selected = setOf(if (state.kind == CallKind.VIDEO) TAB_VIDEO else TAB_VOICE),
                onSelectionChange = { labels ->
                    onSwitchKind(if (TAB_VOICE in labels) CallKind.VOICE else CallKind.VIDEO)
                },
                modifier = Modifier.weight(1f),
            )
            ClIconButton(icon = ClIcons.filter, contentDescription = "筛选条件", onClick = onOpenFilter)
        }

        if (hint.isNotEmpty()) {
            Text(text = hint, style = ClTheme.typography.micro, color = ClTheme.colors.muted)
        }
    }
}

/** 「示例价格 60 币/分钟，接通后计费，未接通不扣费」（设计图 02）。 */
internal fun exampleHint(priceMin: Int): String = if (priceMin <= 0) {
    "接通后计费，未接通不扣费"
} else {
    "示例价格 " + MoneyFormat.coinPrice(priceMin.toLong(), MoneyFormat.UNIT_PER_MINUTE) + "，接通后计费，未接通不扣费"
}

/** 「视频聊 · 120币/分」；服务端没给单价就只写「视频聊」。 */
internal fun callActionText(prefix: String, price: Long?): String {
    if (price == null || price <= 0) {
        return prefix
    }

    return "$prefix · " + MoneyFormat.coinPrice(price, MoneyFormat.UNIT_PER_MINUTE_SHORT)
}

/** 「26 岁 · 上海」——速配卡片位置紧，不带职业。 */
internal fun metaOf(user: DiscoverUser): String = listOfNotNull(
    user.age?.let { "$it 岁" },
    user.city.ifBlank { null },
).joinToString(" · ")
