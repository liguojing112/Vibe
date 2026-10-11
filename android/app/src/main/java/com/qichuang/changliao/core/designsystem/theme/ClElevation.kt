package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/**
 * 阴影 token（docs/03 第 4 节）：全项目只有这一种卡片阴影。
 *
 * Y 偏移 5dp、模糊 20dp、颜色 #F5F1EB；页面里统一用 `Modifier.clCardShadow()`（定义在 ClTheme.kt）。
 */
@Immutable
data class ClElevation(
    val cardOffsetY: Dp,
    val cardBlur: Dp,
)

val ClElevations = ClElevation(
    cardOffsetY = 5.dp,
    cardBlur = 20.dp,
)
