package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Icon
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 图标按钮的可点区域下限（docs/03 第 6 节第 8 条）。 */
private val MinTouchTarget = 48.dp

/**
 * 白色圆形图标按钮（docs/03 第 5.2 节），例如消息页右上角的搜索。
 */
@Composable
fun ClIconButton(
    icon: ImageVector,
    contentDescription: String,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    onClick: () -> Unit,
) {
    Box(
        modifier = modifier
            .size(MinTouchTarget)
            .clip(ClTheme.shapes.circle)
            .background(ClTheme.colors.surface)
            .clickable(enabled = enabled, onClick = onClick),
        contentAlignment = Alignment.Center,
    ) {
        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            tint = ClTheme.colors.ink,
            modifier = Modifier.size(ClTheme.size.iconM),
        )
    }
}
