package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import com.qichuang.changliao.core.designsystem.theme.ClTheme
import com.qichuang.changliao.core.designsystem.theme.clCardShadow

/**
 * 白色圆角卡片 + 统一阴影（docs/03 第 5.3 节）。页面里的卡片一律用它。
 */
@Composable
fun ClCard(
    modifier: Modifier = Modifier,
    shape: Shape = ClTheme.shapes.card,
    color: Color = ClTheme.colors.surface,
    contentPadding: PaddingValues = PaddingValues(ClTheme.spacing.l),
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier = modifier
            .clCardShadow(shape)
            .clip(shape)
            .background(color)
            .padding(contentPadding),
        content = content,
    )
}
