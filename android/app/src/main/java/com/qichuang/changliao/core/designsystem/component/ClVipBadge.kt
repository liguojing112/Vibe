package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * VIP 标识（docs/03 第 5.3 节）：开通了是黄色，没开通是灰色。
 */
@Composable
fun ClVipBadge(
    active: Boolean,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .clip(ClTheme.shapes.pill)
            .background(if (active) ClTheme.colors.primary else ClTheme.colors.line)
            .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xxs),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = "VIP",
            style = ClTheme.typography.micro,
            color = if (active) ClTheme.colors.onPrimary else ClTheme.colors.muted,
            maxLines = 1,
        )
    }
}
