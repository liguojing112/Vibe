package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 提示卡（docs/03 第 5.3 节，对应原型 `.notice-card`）。
 * [highlight] = true 时用浅黄底强调（例如「有 N 人看过你的主页」）。
 */
@Composable
fun ClNoticeCard(
    title: String,
    modifier: Modifier = Modifier,
    content: String? = null,
    highlight: Boolean = false,
) {
    val background = if (highlight) ClTheme.colors.primarySoft else ClTheme.colors.surface
    val borderColor = if (highlight) ClTheme.colors.primaryChipBorder else ClTheme.colors.line

    Column(
        modifier = modifier
            .fillMaxWidth()
            .clip(ClTheme.shapes.cardSmall)
            .background(background)
            .border(1.dp, borderColor, ClTheme.shapes.cardSmall)
            .padding(ClTheme.spacing.l),
        verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.xs),
    ) {
        Text(
            text = title,
            style = ClTheme.typography.cardTitle,
            color = if (highlight) ClTheme.colors.primaryText else ClTheme.colors.ink,
        )
        if (content != null) {
            Text(
                text = content,
                style = ClTheme.typography.caption,
                color = if (highlight) ClTheme.colors.primaryText else ClTheme.colors.inkSecondary,
            )
        }
    }
}
