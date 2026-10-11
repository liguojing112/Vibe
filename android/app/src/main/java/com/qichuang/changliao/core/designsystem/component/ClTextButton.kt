package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 文字链接按钮（docs/03 第 5.2 节，对应原型 `.action`）：例如「收费说明」「查看全部」。
 */
@Composable
fun ClTextButton(
    text: String,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    onClick: (() -> Unit)? = null,
) {
    Text(
        text = text,
        style = ClTheme.typography.caption,
        color = if (enabled) ClTheme.colors.primaryText else ClTheme.colors.muted,
        modifier = modifier
            .clip(RoundedCornerShape(6.dp))
            .clickable(enabled = enabled && onClick != null) { onClick?.invoke() }
            .padding(horizontal = ClTheme.spacing.xs, vertical = ClTheme.spacing.xxs),
    )
}
