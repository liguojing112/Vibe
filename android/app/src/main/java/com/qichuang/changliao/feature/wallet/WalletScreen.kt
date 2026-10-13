package com.qichuang.changliao.feature.wallet

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.designsystem.component.ClBalanceCard
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClCardDivider
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 金币钱包（P13，设计图 13）。
 *
 * 结构：余额大卡 → 充值 / 收支明细两个按钮 → 「金币怎么用」→ 一行说明。
 *
 * **不显示「查看我的收益」**（设计图上有，但 `docs/01` P13 明确写了男用户没有收益、不显示 —— 按文档办）。
 * 价格全部来自服务端（等级配置现算），页面只负责显示（铁律 6）。
 */
@Composable
fun WalletScreen(
    state: WalletUiState,
    onBack: () -> Unit,
    onOpenRecharge: () -> Unit,
    onOpenLogs: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "我的金币", onBack = onBack) },
    ) {
        val coin = state.coin
        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            coin == null -> ClErrorRetry(message = "数据加载失败，请重试", onRetry = onRetry)

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = ClTheme.spacing.l),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
            ) {
                ClBalanceCard(
                    label = "可用金币",
                    amount = state.balance.toString(),
                    desc = state.balanceDesc,
                )

                Row(horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m)) {
                    ClPrimaryButton(
                        text = "充值金币",
                        modifier = Modifier.weight(1f),
                        onClick = onOpenRecharge,
                    )
                    ClSecondaryButton(
                        text = "收支明细",
                        modifier = Modifier.weight(1f),
                        onClick = onOpenLogs,
                    )
                }

                ClSectionHeader(title = "金币怎么用")

                ClCard(contentPadding = PaddingValues()) {
                    // 私信是按对方等级计价的，所以这里给的是区间（docs/01 P13）
                    UsageRow(
                        label = "私信聊天",
                        value = MoneyFormat.coinPriceRange(
                            coin.messagePriceMin.toLong(),
                            coin.messagePriceMax.toLong(),
                            MoneyFormat.UNIT_PER_MESSAGE,
                        ),
                    )
                    if (coin.callEnabled) {
                        UsageRow(
                            label = "语音通话",
                            value = MoneyFormat.coinPriceRange(
                                (coin.voicePriceMin ?: 0).toLong(),
                                (coin.voicePriceMax ?: 0).toLong(),
                                MoneyFormat.UNIT_PER_MINUTE,
                            ),
                        )
                        UsageRow(
                            label = "视频通话",
                            value = MoneyFormat.coinPriceRange(
                                (coin.videoPriceMin ?: 0).toLong(),
                                (coin.videoPriceMax ?: 0).toLong(),
                                MoneyFormat.UNIT_PER_MINUTE,
                            ),
                        )
                    }
                    UsageRow(label = "虚拟礼物", value = "按礼物标价扣费", showDivider = false)
                }

                Text(
                    text = "以上费率由平台配置，以实际扣费为准。",
                    style = ClTheme.typography.tiny,
                    color = ClTheme.colors.muted,
                    modifier = Modifier.padding(bottom = ClTheme.spacing.xl),
                )
            }
        }
    }
}

/** 「金币怎么用」里的一行：左边名称、右边费率（费率的文案由 MoneyFormat 拼）。 */
@Composable
private fun UsageRow(
    label: String,
    value: String,
    showDivider: Boolean = true,
) {
    Box(modifier = Modifier.fillMaxWidth()) {
        ClSettingRow(
            label = label,
            trailing = {
                Text(
                    text = value,
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.ink,
                )
            },
        )
        if (showDivider) {
            ClCardDivider(
                modifier = Modifier
                    .align(Alignment.BottomStart)
                    .padding(start = ClTheme.spacing.l),
            )
        }
    }
}
