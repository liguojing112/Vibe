package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 次按钮底色（docs/03 第 5.2 节写的 `#F4EEE3`，不在颜色 token 表里，故放在这里）。 */
private val SecondaryButtonBackground = androidx.compose.ui.graphics.Color(0xFFF4EEE3)

/**
 * 浅色次按钮（docs/03 第 5.2 节）：`#F4EEE3` 底、ink 字。全项目只有这一种次按钮。
 */
@Composable
fun ClSecondaryButton(
    text: String,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    fillMaxWidth: Boolean = true,
    onClick: () -> Unit,
) {
    Row(
        modifier = modifier
            .then(if (fillMaxWidth) Modifier.fillMaxWidth() else Modifier)
            .height(ClTheme.size.buttonHeight)
            .clip(ClTheme.shapes.pill)
            .background(if (enabled) SecondaryButtonBackground else ClTheme.colors.line)
            .clickable(enabled = enabled, onClick = onClick)
            .padding(horizontal = ClTheme.spacing.l),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(
            text = text,
            style = ClTheme.typography.button,
            color = if (enabled) ClTheme.colors.ink else ClTheme.colors.muted,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
