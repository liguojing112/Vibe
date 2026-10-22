package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.runtime.Immutable
import androidx.compose.ui.graphics.Color

/**
 * 颜色 token（docs/03 第 2 节，数值逐个照文档）。
 *
 * 页面里**只能**用 `ClTheme.colors.xxx`，禁止写死色值（check-ui.sh 会扫描）。
 * 不使用深色模式，强制浅色。
 */
@Immutable
data class ClColors(
    val primary: Color,
    val onPrimary: Color,
    val primarySoft: Color,
    val primaryChip: Color,
    val primaryChipBorder: Color,
    val primaryText: Color,
    val accentUnderline: Color,
    val ink: Color,
    val inkSecondary: Color,
    val muted: Color,
    val line: Color,
    val bgCream: Color,
    val bgPage: Color,
    val bgChat: Color,
    val bgInput: Color,
    val surface: Color,
    val pink: Color,
    val orange: Color,
    val green: Color,
    val onlineDot: Color,
    val danger: Color,
    val onDanger: Color,
    val hangup: Color,
    val coinBg: Color,
    val coinText: Color,
    val incomeText: Color,
    val tagGreenBg: Color,
    val tagGreenText: Color,
    val tagGoldBg: Color,
    val tagGoldText: Color,
    val tagPinkBg: Color,
    val tagPinkText: Color,
    val bubbleMine: Color,
    val bubbleOther: Color,
    val feeStripBg: Color,
    val feeStripText: Color,
    val callBgDark: Color,
    val mask: Color,
)

/** 强制浅色。 */
val ClLightColors = ClColors(
    primary = Color(0xFFFFD839),
    onPrimary = Color(0xFF2F2615),
    primarySoft = Color(0xFFFFF0C4),
    primaryChip = Color(0xFFFFF5CF),
    primaryChipBorder = Color(0xFFFFC944),
    primaryText = Color(0xFF9B7218),
    accentUnderline = Color(0xFFFFC425),
    ink = Color(0xFF24231F),
    inkSecondary = Color(0xFF77736C),
    muted = Color(0xFFAAA69F),
    line = Color(0xFFF1EDE7),
    bgCream = Color(0xFFFFFAF1),
    bgPage = Color(0xFFF8F8F7),
    bgChat = Color(0xFFFAF8F5),
    bgInput = Color(0xFFF5F3F0),
    surface = Color(0xFFFFFFFF),
    pink = Color(0xFFFF6D91),
    orange = Color(0xFFFF982A),
    green = Color(0xFF33C890),
    onlineDot = Color(0xFF2EDB73),
    danger = Color(0xFFFB3E48),
    onDanger = Color(0xFFFFFFFF),
    hangup = Color(0xFFFC6266),
    coinBg = Color(0xFFFFF0BD),
    coinText = Color(0xFF987015),
    incomeText = Color(0xFFB78A31),
    tagGreenBg = Color(0xFFDDF9E9),
    tagGreenText = Color(0xFF22B868),
    tagGoldBg = Color(0xFFFFF0CF),
    tagGoldText = Color(0xFFDB8B1D),
    tagPinkBg = Color(0xFFFFE6EE),
    tagPinkText = Color(0xFFE95879),
    bubbleMine = Color(0xFFFFDE5E),
    bubbleOther = Color(0xFFFFFFFF),
    feeStripBg = Color(0xFFFFF5D9),
    feeStripText = Color(0xFF9B781E),
    callBgDark = Color(0xFF2A2928),
    // #201B12 透明度 43% → alpha 0.43
    mask = Color(0x6E201B12),
)
