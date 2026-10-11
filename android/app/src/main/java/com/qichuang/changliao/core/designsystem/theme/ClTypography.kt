package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

/**
 * 文字 token（docs/03 第 3 节，字号/字重逐个照文档）。字体用系统默认。
 * 颜色不在这里写死，用的时候配 `ClTheme.colors.xxx`。
 */
@Immutable
data class ClTypography(
    val pageTitle: TextStyle,
    val headerTitle: TextStyle,
    val sectionTitle: TextStyle,
    val cardTitle: TextStyle,
    val body: TextStyle,
    val bodySmall: TextStyle,
    val caption: TextStyle,
    val tiny: TextStyle,
    val micro: TextStyle,
    val amountLarge: TextStyle,
    val amountMedium: TextStyle,
    val button: TextStyle,
    val buttonSmall: TextStyle,
)

val ClTextStyles = ClTypography(
    pageTitle = TextStyle(fontSize = 25.sp, fontWeight = FontWeight.Bold),
    headerTitle = TextStyle(fontSize = 17.sp, fontWeight = FontWeight.Bold),
    sectionTitle = TextStyle(fontSize = 17.sp, fontWeight = FontWeight.Bold),
    cardTitle = TextStyle(fontSize = 16.sp, fontWeight = FontWeight.Bold),
    body = TextStyle(fontSize = 14.sp, fontWeight = FontWeight.Normal),
    bodySmall = TextStyle(fontSize = 13.sp, fontWeight = FontWeight.Normal),
    caption = TextStyle(fontSize = 12.sp, fontWeight = FontWeight.Normal),
    tiny = TextStyle(fontSize = 11.sp, fontWeight = FontWeight.Normal),
    micro = TextStyle(fontSize = 10.sp, fontWeight = FontWeight.Normal),
    amountLarge = TextStyle(fontSize = 39.sp, fontWeight = FontWeight.Bold),
    amountMedium = TextStyle(fontSize = 27.sp, fontWeight = FontWeight.Bold),
    button = TextStyle(fontSize = 14.sp, fontWeight = FontWeight.Bold),
    buttonSmall = TextStyle(fontSize = 12.sp, fontWeight = FontWeight.Medium),
)
