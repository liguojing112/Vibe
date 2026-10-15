package com.qichuang.changliao.feature.match

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClGradientHero
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClMiniProfileRow
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.theme.ClGradients
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * P16 语音大厅（docs/01 P16，设计图 16）：渐变横幅 + 能接听的人 + 每人一颗「语音聊」。
 *
 * 和 P02 是同一个数据源（`/discover` 的 `voice` Tab），只是这里用列表展示、用语音单价。
 * 点「语音聊」→ P17（确认拨打只会提示「功能暂未开放」，不发请求、不扣币）。
 */
@Composable
fun VoiceHallScreen(
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
                title = "听见心动",
                subtitle = "一对一语音，给心情一个温柔的回应",
                // 价格写在下面的横幅里（设计图 16），页头就不再重复一遍
                hint = "",
                onSwitchKind = onSwitchKind,
                onOpenFilter = onOpenFilter,
                onOpenWallet = onOpenWallet,
            )

            when {
                state.loading -> ClLoading()

                state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                state.isEmpty -> ClEmpty(
                    title = "现在没人语音在线",
                    desc = "过一会儿再来看看",
                    actionText = "刷新",
                    onAction = onRetry,
                )

                else -> LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(bottom = ClTheme.spacing.l),
                ) {
                    item {
                        ClGradientHero(
                            gradient = ClGradients.roomHero,
                            title = "有空，一起聊会儿？",
                            subtitle = bannerSubtitle(state.priceMin),
                            modifier = Modifier.padding(
                                start = ClTheme.spacing.l,
                                end = ClTheme.spacing.l,
                                top = ClTheme.spacing.xs,
                                bottom = ClTheme.spacing.m,
                            ),
                        )
                    }

                    items(state.users, key = { it.id }) { user ->
                        ClMiniProfileRow(
                            name = user.nickname,
                            avatarUrl = user.avatar.ifBlank { null },
                            desc = metaOf(user),
                            online = user.online,
                            tagText = "可接听",
                            buttonText = callActionText("语音聊", user.voicePrice),
                            onClick = { onOpenUser(user.id) },
                            onAction = { onCall(user.id) },
                        )
                    }
                }
            }
        }
    }
}

/** 「60 币/分钟 · 接通后计费 · 示例价格」（设计图 16 的横幅）。 */
private fun bannerSubtitle(priceMin: Int): String {
    val price = if (priceMin <= 0) {
        "示例价格"
    } else {
        MoneyFormat.coinPrice(priceMin.toLong(), MoneyFormat.UNIT_PER_MINUTE) + " · 示例价格"
    }

    return "$price · 接通后计费"
}
