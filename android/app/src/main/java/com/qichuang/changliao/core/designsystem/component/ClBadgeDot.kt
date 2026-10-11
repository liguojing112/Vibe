package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private val BadgeHeight = 16.dp

/**
 * 红色未读数角标（docs/03 第 5.3 节）：>99 显示 99+，<=0 不显示。
 */
@Composable
fun ClBadgeDot(
    count: Int,
    modifier: Modifier = Modifier,
) {
    if (count <= 0) {
        return
    }

    Box(
        modifier = modifier
            .height(BadgeHeight)
            .widthIn(min = BadgeHeight)
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.danger)
            .padding(horizontal = ClTheme.spacing.xxs),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = if (count > 99) "99+" else count.toString(),
            style = ClTheme.typography.micro,
            color = ClTheme.colors.surface,
            maxLines = 1,
        )
    }
}
