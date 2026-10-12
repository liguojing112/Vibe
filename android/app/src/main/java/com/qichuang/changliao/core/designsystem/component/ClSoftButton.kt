package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextOverflow
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 卡片里的小操作按钮的三种底色（docs/03 第 5.2 节：语音/视频/私信）。
 *
 * 原型 CSS 里的这三组底色没法定死（暖色底非常多），先映射到最接近的颜色 token；
 * 具体哪个页面用哪一种，等 T3.x 对着设计图定（已记入 PROGRESS 待确认）。
 */
enum class ClSoftButtonKind {
    Yellow,
    Peach,
    Gray,
}

/**
 * 卡片里的小操作按钮（docs/03 第 5.2 节）。
 */
@Composable
fun ClSoftButton(
    text: String,
    modifier: Modifier = Modifier,
    kind: ClSoftButtonKind = ClSoftButtonKind.Yellow,
    leadingIcon: ImageVector? = null,
    enabled: Boolean = true,
    onClick: () -> Unit,
) {
    val background = when (kind) {
        ClSoftButtonKind.Yellow -> ClTheme.colors.primarySoft
        ClSoftButtonKind.Peach -> ClTheme.colors.tagGoldBg
        ClSoftButtonKind.Gray -> ClTheme.colors.bgInput
    }
    val foreground = when (kind) {
        ClSoftButtonKind.Yellow -> ClTheme.colors.primaryText
        ClSoftButtonKind.Peach -> ClTheme.colors.tagGoldText
        ClSoftButtonKind.Gray -> ClTheme.colors.inkSecondary
    }

    Row(
        modifier = modifier
            .height(ClTheme.size.buttonHeightSmall)
            .clip(ClTheme.shapes.pill)
            .background(background)
            .clickable(enabled = enabled, onClick = onClick)
            .padding(horizontal = ClTheme.spacing.m),
        horizontalArrangement = Arrangement.Center,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (leadingIcon != null) {
            Icon(
                imageVector = leadingIcon,
                contentDescription = null,
                tint = foreground,
                modifier = Modifier.size(ClTheme.size.iconS),
            )
            Spacer(modifier = Modifier.width(ClTheme.spacing.xs))
        }
        Text(
            text = text,
            style = ClTheme.typography.buttonSmall,
            color = foreground,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
    }
}
