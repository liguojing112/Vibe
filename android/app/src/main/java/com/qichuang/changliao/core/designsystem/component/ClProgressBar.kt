package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.qichuang.changliao.core.designsystem.theme.ClTheme

private val ProgressHeight = 6.dp

/**
 * 等级进度条（docs/03 第 5.3 节）：[progress] 取 0f~1f，超出会被夹到区间内。
 */
@Composable
fun ClProgressBar(
    progress: Float,
    modifier: Modifier = Modifier,
    height: Dp = ProgressHeight,
) {
    val value = progress.coerceIn(0f, 1f)

    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(height)
            .clip(ClTheme.shapes.pill)
            .background(ClTheme.colors.line),
    ) {
        Box(
            modifier = Modifier
                .fillMaxHeight()
                .fillMaxWidth(value)
                .clip(ClTheme.shapes.pill)
                .background(ClTheme.colors.primary),
        )
    }
}
