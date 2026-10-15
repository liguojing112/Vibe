package com.qichuang.changliao.feature.call

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClBillGroup
import com.qichuang.changliao.core.designsystem.component.ClBillRow
import com.qichuang.changliao.core.designsystem.component.ClBottomSheet
import com.qichuang.changliao.core.designsystem.component.ClDemoLabel
import com.qichuang.changliao.core.designsystem.component.ClEmpty
import com.qichuang.changliao.core.designsystem.component.ClErrorRetry
import com.qichuang.changliao.core.designsystem.component.ClFixedBottomArea
import com.qichuang.changliao.core.designsystem.component.ClLoading
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * P17 拨打前价格确认（docs/01 P17，设计图 17）。
 *
 * **点「确认拨打」只弹提示，不发请求不扣币**（`01` 第 4 节）。
 * 为了能走查后面的 P18~P21，**debug 包里长按「确认拨打」**进 P18 演示（`01` P17 要求）。
 */
@Composable
fun CallConfirmScreen(
    state: CallUiState,
    onBack: () -> Unit,
    onConfirm: () -> Unit,
    /** 长按「确认拨打」——只有 debug 包会传进来，正式包传 null（长按什么都不发生）。 */
    onDemo: (() -> Unit)?,
    onToastShown: () -> Unit,
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier, containerColor = ClTheme.colors.bgCream) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        // 「查看完整收费说明」是**本页的弹层**，不是跳页面：完整说明就是下面那段计费规则，
        // 没有单独的页面（docs/01 的页面清单里也没有）
        var rulesVisible by remember { mutableStateOf(false) }

        Column(modifier = Modifier.fillMaxSize()) {
            ClTopBar(title = "确认" + state.kind.callLabel, onBack = onBack)

            when {
                state.loading -> ClLoading()

                state.error != null -> ClErrorRetry(message = state.error, onRetry = onRetry)

                state.peerName.isEmpty() -> ClEmpty(title = "找不到这个人")

                else -> Column(modifier = Modifier.weight(1f)) {
                    PeerHeader(state)

                    ClBillGroup(
                        modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                    ) {
                        ClBillRow(label = "通话方式", value = state.callTypeText)
                        ClBillRow(label = "单价", value = state.priceText)
                        ClBillRow(label = "当前余额", value = state.balanceText)
                        ClBillRow(label = "最多可聊", value = state.maxMinutesText, showDivider = false)
                    }

                    Text(
                        text = FEE_SUMMARY,
                        style = ClTheme.typography.caption,
                        color = ClTheme.colors.inkSecondary,
                        modifier = Modifier.padding(
                            start = ClTheme.spacing.l,
                            end = ClTheme.spacing.l,
                            top = ClTheme.spacing.m,
                        ),
                    )

                    ClTextButton(
                        text = "查看完整收费说明 ›",
                        onClick = { rulesVisible = true },
                        modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                    )

                    ClDemoLabel(
                        text = "原型演示 · 示例费率 · 不产生真实费用",
                        modifier = Modifier.padding(horizontal = ClTheme.spacing.l),
                    )
                }
            }

            ClFixedBottomArea {
                ClPrimaryButton(
                    text = "确认拨打",
                    modifier = Modifier.weight(1f),
                    subText = state.confirmSubText.ifBlank { null },
                    enabled = !state.loading && state.error == null,
                    onLongClick = onDemo,
                    onClick = onConfirm,
                )
            }
        }

        if (rulesVisible) {
            ClBottomSheet(title = "完整收费说明", onClose = { rulesVisible = false }) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = ClTheme.spacing.l),
                    verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
                ) {
                    Text(
                        text = FEE_SUMMARY,
                        style = ClTheme.typography.body,
                        color = ClTheme.colors.ink,
                    )
                    Text(
                        text = "本期语音/视频功能尚未开放，页面上不产生任何真实费用。",
                        style = ClTheme.typography.caption,
                        color = ClTheme.colors.inkSecondary,
                    )
                }
            }
        }
    }
}

/** 计费说明（设计图 17 那段原文）。 */
internal const val FEE_SUMMARY =
    "接通后开始计费；未接通不扣费。每开始一分钟扣除一次单价，不足一分钟按一分钟计算。" +
        "余额不足以开始下一分钟时，通话会结束。"

@Composable
private fun PeerHeader(state: CallUiState) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(ClTheme.spacing.l),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
    ) {
        ClAvatar(
            url = state.peerAvatar,
            size = ClTheme.size.avatarL,
            online = state.peerOnline,
            contentDescription = state.peerName,
        )

        Column(verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs)) {
            Text(
                text = state.peerName,
                style = ClTheme.typography.pageTitle,
                color = ClTheme.colors.ink,
            )
            Text(
                text = peerSubtitle(state),
                style = ClTheme.typography.caption,
                color = ClTheme.colors.inkSecondary,
            )
        }
    }
}

/** 「在线可接听 · 已真人认证」——没在线/没实名就少写一截。 */
private fun peerSubtitle(state: CallUiState): String = listOfNotNull(
    if (state.peerOnline) "在线可接听" else null,
    if (state.peerVerified) "已真人认证" else null,
).joinToString(" · ").ifBlank { "暂时联系不上" }
