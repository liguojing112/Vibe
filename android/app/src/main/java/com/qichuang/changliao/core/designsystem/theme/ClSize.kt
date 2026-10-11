package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

/**
 * 尺寸 token（docs/03 第 4 节）。
 */
@Immutable
data class ClSize(
    val topBarHeight: Dp,
    val bottomBarHeight: Dp,
    val buttonHeight: Dp,
    val buttonHeightSmall: Dp,
    val inputHeight: Dp,
    val menuRowHeight: Dp,
    val avatarL: Dp,
    val avatarM: Dp,
    val avatarS: Dp,
    val avatarXS: Dp,
    val iconM: Dp,
    val iconS: Dp,
    val onlineDot: Dp,
    val coverHeight: Dp,
)

val ClSizes = ClSize(
    topBarHeight = 56.dp,
    bottomBarHeight = 68.dp,
    buttonHeight = 46.dp,
    buttonHeightSmall = 32.dp,
    inputHeight = 44.dp,
    menuRowHeight = 55.dp,
    avatarL = 72.dp,
    avatarM = 60.dp,
    avatarS = 50.dp,
    avatarXS = 30.dp,
    iconM = 22.dp,
    iconS = 17.dp,
    onlineDot = 13.dp,
    coverHeight = 290.dp,
)
