package com.qichuang.changliao.feature.call

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextAlign
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.designsystem.component.ClBillGroup
import com.qichuang.changliao.core.designsystem.component.ClBillRow
import com.qichuang.changliao.core.designsystem.component.ClDemoLabel
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClSecondaryButton
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * P21 结束通话账单（docs/01 P21，设计图 21）：**本地假账单**。
 *
 * 数字全部来自 P19/P20 那个本地计时器（[CallBill]），**没有真的扣过币** ——
 * 「查看明细」进的是真的金币流水页（P24），那里不会有这次通话的记录，
 * 这正是「只做页面、不做功能」该有的样子。
 */
@Composable
fun CallBillScreen(
    peerName: String,
    kind: CallKind,
    bill: CallBill,
    onBack: () -> Unit,
    onOpenLogs: () -> Unit,
    onContinueChat: () -> Unit,
    onCallAgain: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier, containerColor = ClTheme.colors.bgCream) {
        Column(modifier = Modifier.fillMaxSize()) {
            ClTopBar(title = "通话账单", onBack = onBack)

            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth()
                    .padding(horizontal = ClTheme.spacing.l),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                ClDemoLabel(modifier = Modifier.padding(top = ClTheme.spacing.s))

                Box(
                    modifier = Modifier
                        .padding(top = ClTheme.spacing.xxl)
                        .size(ClTheme.size.avatarS)
                        .clip(ClTheme.shapes.circle)
                        .background(ClTheme.colors.primary),
                    contentAlignment = Alignment.Center,
                ) {
                    Icon(
                        imageVector = ClIcons.check,
                        contentDescription = null,
                        tint = ClTheme.colors.onPrimary,
                        modifier = Modifier.size(ClTheme.size.iconM),
                    )
                }

                Text(
                    text = "通话已结束",
                    style = ClTheme.typography.pageTitle,
                    color = ClTheme.colors.ink,
                    modifier = Modifier.padding(top = ClTheme.spacing.m),
                )
                Text(
                    text = "与 $peerName 的" + kind.label + "聊天",
                    style = ClTheme.typography.caption,
                    color = ClTheme.colors.inkSecondary,
                    modifier = Modifier.padding(top = ClTheme.spacing.xs),
                )

                ClBillGroup(modifier = Modifier.padding(top = ClTheme.spacing.xl)) {
                    ClBillRow(label = "通话时长", value = CallFormat.duration(bill.seconds))
                    ClBillRow(label = "计费单价", value = "${bill.price} 币/分钟")
                    ClBillRow(label = "计费分钟数", value = "${bill.minutes} 分钟")
                    ClBillRow(label = "本次通话扣费", value = "${bill.charged} 金币")
                    ClBillRow(label = "当前余额", value = "${bill.balanceAfter} 金币", showDivider = false)
                }

                Text(
                    text = "礼物费用单独记录。可在金币收支明细中查看每笔消费。",
                    style = ClTheme.typography.micro,
                    color = ClTheme.colors.muted,
                    textAlign = TextAlign.Start,
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = ClTheme.spacing.m),
                )

                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = ClTheme.spacing.xl),
                    horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
                ) {
                    ClSecondaryButton(
                        text = "查看明细",
                        modifier = Modifier.weight(1f),
                        onClick = onOpenLogs,
                    )
                    ClPrimaryButton(
                        text = "继续私信",
                        modifier = Modifier.weight(1f),
                        onClick = onContinueChat,
                    )
                }

                ClSecondaryButton(
                    text = "再次拨打",
                    modifier = Modifier.padding(top = ClTheme.spacing.s),
                    onClick = onCallAgain,
                )
            }
        }
    }
}
