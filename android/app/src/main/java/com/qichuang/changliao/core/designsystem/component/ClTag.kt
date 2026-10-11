package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 标签颜色（docs/03 第 5.3 节）。 */
enum class ClTagKind {
    Green,
    Gold,
    Pink,
}

/**
 * 小标签（docs/03 第 5.3 节，对应原型 `.tag`）：在线 / 已认证 / 新人 等。
 */
@Composable
fun ClTag(
    text: String,
    modifier: Modifier = Modifier,
    kind: ClTagKind = ClTagKind.Green,
) {
    val background: Color
    val foreground: Color
    when (kind) {
        ClTagKind.Green -> {
            background = ClTheme.colors.tagGreenBg
            foreground = ClTheme.colors.tagGreenText
        }
        ClTagKind.Gold -> {
            background = ClTheme.colors.tagGoldBg
            foreground = ClTheme.colors.tagGoldText
        }
        ClTagKind.Pink -> {
            background = ClTheme.colors.tagPinkBg
            foreground = ClTheme.colors.tagPinkText
        }
    }

    Text(
        text = text,
        style = ClTheme.typography.micro,
        color = foreground,
        maxLines = 1,
        modifier = modifier
            .clip(RoundedCornerShape(20.dp))
            .background(background)
            .padding(horizontal = ClTheme.spacing.s, vertical = ClTheme.spacing.xxs),
    )
}
