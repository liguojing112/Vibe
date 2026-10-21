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
    val iconL: Dp,
    val iconM: Dp,
    val iconS: Dp,
    val onlineDot: Dp,
    /** 头像横条里一项的宽度（P03 的「最近联系」）：比头像略宽一点，昵称才不会挤成一团。 */
    val peerStripItem: Dp,
    val coverHeight: Dp,

    /** 页面内容的最大宽度：平板/横屏时不要把输入框拉成一整行。 */
    val contentMaxWidth: Dp,

    /** 低于这个高度算「矮屏」（横屏、小屏），页面收紧留白。 */
    val compactHeight: Dp,
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
    iconL = 30.dp,
    iconM = 22.dp,
    iconS = 17.dp,
    onlineDot = 13.dp,
    peerStripItem = 76.dp,
    coverHeight = 290.dp,
    contentMaxWidth = 420.dp,
    compactHeight = 600.dp,
)
