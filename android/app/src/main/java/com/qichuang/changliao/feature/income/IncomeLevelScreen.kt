package com.qichuang.changliao.feature.income

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.IncomeLevelRow
import com.qichuang.changliao.core.designsystem.component.ClCard
import com.qichuang.changliao.core.designsystem.component.ClCardDivider
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClNoticeCard
import com.qichuang.changliao.core.designsystem.component.ClProgressBar
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSectionHeader
import com.qichuang.changliao.core.designsystem.component.ClSettingRow
import com.qichuang.changliao.core.designsystem.component.ClSwitch
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 我的等级与收费（P27，设计图 27）。
 *
 * 结构：等级卡（当前等级 + 累计收益 + 距下一级进度）→ 我的收费标准（只读）→ 等级表
 * → 在线接听开关。
 *
 * 两处按文档而不是设计图办：
 * 1. 设计图是「接听与收费设置」（可选价格），本期按 `docs/01` P27 改成**只读展示** ——
 *    价格由后台按等级统一定；
 * 2. 「在线接听」开关保留，但它的含义是**是否出现在发现页的推荐里**（`docs/01` P27 原文）。
 */
@Composable
fun IncomeLevelScreen(
    state: IncomeLevelUiState,
    onBack: () -> Unit,
    onOpenRealName: () -> Unit,
    onToggleOnline: (Boolean) -> Unit,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = { ClTopBar(title = "我的等级与收费", onBack = onBack) },
    ) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        when {
            state.loading -> ClLoading()

            state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

            state.isEmpty -> ClErrorRetry(message = "数据加载失败，请重试", onRetry = onRetry)

            else -> Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(
                        start = ClTheme.spacing.l,
                        end = ClTheme.spacing.l,
                        bottom = ClTheme.spacing.xl,
                    ),
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                Column(
                    modifier = Modifier.padding(top = ClTheme.spacing.l),
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                ) {
                    Text(
                        text = state.levelText,
                        style = ClTheme.typography.sectionTitle,
                        color = ClTheme.colors.ink,
                    )
                    Text(
                        text = "累计收益 ${state.earnedText}",
                        style = ClTheme.typography.bodySmall,
                        color = ClTheme.colors.inkSecondary,
                    )
                    ClProgressBar(progress = state.progress)
                    Text(
                        text = state.progressText,
                        style = ClTheme.typography.tiny,
                        color = ClTheme.colors.muted,
                    )
                }

                ClSectionHeader(title = "我的收费标准")
                ClCard {
                    ClSettingRow(
                        label = "私信单价",
                        desc = "由我的等级决定",
                        trailing = { ValueText(state.messagePriceText) },
                    )
                    ClCardDivider()
                    ClSettingRow(
                        label = "我的分成比例",
                        trailing = { ValueText(state.shareText) },
                    )
                    ClCardDivider()
                    ClSettingRow(
                        label = "礼物分成比例",
                        trailing = { ValueText(state.giftShareText) },
                        desc = "礼物分成不随等级变化",
                    )
                    if (state.callEnabled) {
                        ClCardDivider()
                        ClSettingRow(label = "语音单价", trailing = { ValueText(state.voicePriceText) })
                        ClCardDivider()
                        ClSettingRow(label = "视频单价", trailing = { ValueText(state.videoPriceText) })
                    }
                }

                if (state.showRealNameHint) {
                    ClNoticeCard(
                        title = "完成实名认证可以提高分成",
                        content = state.shareHint,
                        highlight = true,
                    )
                    ClTextButton(
                        text = "去完成实名认证",
                        modifier = Modifier.fillMaxWidth(),
                        onClick = onOpenRealName,
                    )
                }

                ClSectionHeader(title = "等级表")
                ClCard(contentPadding = PaddingValues()) {
                    state.level?.levels?.forEachIndexed { index, row ->
                        LevelRow(row = row, showDivider = index != state.level?.levels?.lastIndex)
                    }
                }

                ClSectionHeader(title = "接听设置")
                ClCard {
                    ClSettingRow(
                        label = "在线接听",
                        desc = "关闭后不会出现在发现页的推荐里",
                        trailing = {
                            ClSwitch(
                                checked = state.showInRecommend,
                                enabled = !state.switching,
                                onCheckedChange = onToggleOnline,
                            )
                        },
                    )
                }
            }
        }
    }
}

/** 等级表的一行：Lv + 门槛 + 单价 + 分成。 */
@Composable
private fun LevelRow(row: IncomeLevelRow, showDivider: Boolean) {
    ClSettingRow(
        label = row.name,
        desc = "升级门槛 ${MoneyFormat.points(row.thresholdPoints)}",
        trailing = {
            ValueText(
                "${MoneyFormat.coinPrice(row.messagePrice.toLong(), MoneyFormat.UNIT_PER_MESSAGE)} · " +
                    MoneyFormat.percent(row.messageShareRate),
            )
        },
    )
    if (showDivider) {
        ClCardDivider()
    }
}

@Composable
private fun ValueText(value: String) {
    Text(
        text = value,
        style = ClTheme.typography.body,
        color = ClTheme.colors.ink,
    )
}
