package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 黄色描边小按钮（docs/03 第 5.2 节，对应原型 `.say-hi`）：打招呼、查看、回关。
 */
@Composable
fun ClOutlineSmallButton(
    text: String,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    onClick: () -> Unit,
) {
    val borderColor = if (enabled) ClTheme.colors.primaryChipBorder else ClTheme.colors.line
    val textColor = if (enabled) ClTheme.colors.primaryText else ClTheme.colors.muted

    Row(
        modifier = modifier
            .height(ClTheme.size.buttonHeightSmall)
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.surface)
            .border(1.dp, borderColor, ClTheme.shapes.pill)
            .clickable(enabled = enabled, onClick = onClick)
            .padding(horizontal = ClTheme.spacing.m),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = text,
            style = ClTheme.typography.buttonSmall,
            color = textColor,
            maxLines = 1,
        )
    }
}
