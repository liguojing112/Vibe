package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/**
 * 间距 token（docs/03 第 4 节）。
 * 页面左右边距统一 `l`；卡片内边距统一 `l`；列表项之间 `m`；区块之间 `xl`。
 */
@Immutable
data class ClSpacing(
    val xxs: Dp,
    val xs: Dp,
    val s: Dp,
    val m: Dp,
    val l: Dp,
    val xl: Dp,
    val xxl: Dp,
    val xxxl: Dp,
)

val ClSpacings = ClSpacing(
    xxs = 2.dp,
    xs = 4.dp,
    s = 8.dp,
    m = 12.dp,
    l = 16.dp,
    xl = 20.dp,
    xxl = 24.dp,
    xxxl = 32.dp,
)
