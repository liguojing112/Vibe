package com.qichuang.changliao.feature.call

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.designsystem.component.ClAvatar
import com.qichuang.changliao.core.designsystem.component.ClDemoLabel
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.icon.ClIcons
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 深色底上胶囊的透明度（设计图 18~20 是半透明白）。 */
internal const val DARK_CHIP_ALPHA = 0.16f

/**
 * P18 等待接通（docs/01 P18，设计图 18）：**本地假数据，没有任何网络请求**。
 *
 * 深色界面 + 顶部「演示界面」小标签（`01` 第 4 节要求 P18~P21 都带）。
 * 两颗「模拟」按钮是**演示专用**：真实产品里接通与否是对面决定的，
 * 现在没有音视频服务，只能靠手点才能走到 P19/P20/P21。
 */
@Composable
fun CallWaitingScreen(
    peerName: String,
    peerAvatar: String?,
    kind: CallKind,
    price: Long,
    onAccepted: () -> Unit,
    onNoAnswer: () -> Unit,
    onCancel: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier, containerColor = ClTheme.colors.callBgDark) {
        Column(modifier = Modifier.fillMaxSize()) {
            // `01` 第 4 节要求 P18~P21「页面顶部显示「演示界面」小标签」
            ClDemoLabel(
                modifier = Modifier.padding(
                    start = ClTheme.spacing.l,
                    top = ClTheme.spacing.l,
                ),
            )

            CallTopBar(title = kind.callLabel + "邀请", chipText = "等待接通 · 未计费")

            Column(
                modifier = Modifier
                    .weight(1f)
                    .fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                ClAvatar(
                    url = peerAvatar,
                    size = ClTheme.size.avatarL,
                    contentDescription = peerName,
                )

                Text(
                    text = peerName,
                    style = ClTheme.typography.pageTitle,
                    color = ClTheme.colors.surface,
                    modifier = Modifier.padding(top = ClTheme.spacing.l),
                )
                Text(
                    text = "正在邀请对方" + kind.label + "聊天…",
                    style = ClTheme.typography.body,
                    color = ClTheme.colors.surface,
                    modifier = Modifier.padding(top = ClTheme.spacing.s),
                )

                if (price > 0) {
                    CallChip(
                        text = "接通后 $price 金币/分钟",
                        modifier = Modifier.padding(top = ClTheme.spacing.l),
                    )
                }

                CallOutlineButton(
                    text = "模拟对方接听",
                    onClick = onAccepted,
                    modifier = Modifier.padding(top = ClTheme.spacing.xxl),
                )
                CallOutlineButton(
                    text = "模拟无人接听",
                    onClick = onNoAnswer,
                    modifier = Modifier.padding(top = ClTheme.spacing.m),
                )
            }

            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = ClTheme.spacing.xxl),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
            ) {
                HangupButton(text = "取消呼叫", onClick = onCancel)

                Text(
                    text = "这是等待接通的界面演示",
                    style = ClTheme.typography.micro,
                    color = ClTheme.colors.muted,
                )
            }
        }
    }
}

/** 深色页面顶栏：左边标题、右边状态胶囊（P18/P19/P20 共用）。 */
@Composable
internal fun CallTopBar(title: String, chipText: String, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(ClTheme.spacing.l),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = title,
            style = ClTheme.typography.headerTitle,
            color = ClTheme.colors.surface,
            modifier = Modifier.weight(1f),
        )
        CallChip(text = chipText)
    }
}

/** 深色底上的半透明胶囊（P18 的「等待接通 · 未计费」、P19/P20 的计费条）。 */
@Composable
internal fun CallChip(text: String, modifier: Modifier = Modifier) {
    Text(
        text = text,
        style = ClTheme.typography.micro,
        color = ClTheme.colors.surface,
        modifier = modifier
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.surface.copy(alpha = DARK_CHIP_ALPHA))
            .padding(horizontal = ClTheme.spacing.m, vertical = ClTheme.spacing.xs),
    )
}

/** 深色底上的半透明按钮（P18 的两颗「模拟」）。 */
@Composable
internal fun CallOutlineButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Text(
        text = text,
        style = ClTheme.typography.body,
        color = ClTheme.colors.surface,
        modifier = modifier
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.surface.copy(alpha = DARK_CHIP_ALPHA))
            .clickable(onClick = onClick)
            .padding(horizontal = ClTheme.spacing.xxl, vertical = ClTheme.spacing.m),
    )
}

/** 挂断/取消呼叫按钮（P18 的「取消呼叫」、P19/P20 的「结束通话」）。 */
@Composable
internal fun HangupButton(text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.hangup)
            .clickable(onClick = onClick)
            .padding(horizontal = ClTheme.spacing.xxl, vertical = ClTheme.spacing.m),
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(ClTheme.spacing.s),
        ) {
            Icon(
                imageVector = ClIcons.hangup,
                contentDescription = null,
                tint = ClTheme.colors.surface,
                modifier = Modifier.size(ClTheme.size.iconM),
            )
            Text(
                text = text,
                style = ClTheme.typography.button,
                color = ClTheme.colors.surface,
            )
        }
    }
}
