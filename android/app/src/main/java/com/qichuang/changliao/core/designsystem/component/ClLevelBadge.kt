package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import com.qichuang.changliao.core.designsystem.theme.ClGradients
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.designsystem.theme.clGradientBackground

/**
 * 紫色渐变等级徽章（docs/03 第 5.3 节，对应原型 `.level`）。
 */
@Composable
fun ClLevelBadge(
    level: Int,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier = modifier
            .clip(ClTheme.shapes.pill)
            .clGradientBackground(ClGradients.level)
            .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xxs),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = "Lv$level",
            style = ClTheme.typography.micro,
            color = ClTheme.colors.surface,
            maxLines = 1,
        )
    }
}
