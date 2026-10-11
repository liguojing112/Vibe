package com.qichuang.changliao.core.designsystem.theme

import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.drawscope.DrawScope
import kotlin.math.abs
import kotlin.math.cos
import kotlin.math.hypot
import kotlin.math.max
import kotlin.math.sin

/**
 * 渐变（docs/03 第 2 节）。数值来自 HTML 原型 `遇见你-收费聊天版.html` 的 CSS。
 *
 * Compose 的 Brush 尺寸相关，没法像 CSS 那样只写角度/百分比，所以这里定义「各层颜色 + 角度/位置」，
 * 再由 [drawClGradient] / [drawClTopWash] 按实际尺寸画出来。
 */
object ClGradients {

    /** linear-gradient(120deg,#fff1b0,#ffd667) 金币余额大卡、收益大卡 */
    val balance = ClLinearGradient(120f, listOf(Color(0xFFFFF1B0), Color(0xFFFFD667)))

    /** linear-gradient(110deg,#ffcf3e,#ff9a40) 横幅 */
    val banner = ClLinearGradient(110f, listOf(Color(0xFFFFCF3E), Color(0xFFFF9A40)))

    /** linear-gradient(130deg,#ffdc55,#ffac8d) 任务页、语音大厅横幅 */
    val roomHero = ClLinearGradient(130f, listOf(Color(0xFFFFDC55), Color(0xFFFFAC8D)))

    /** linear-gradient(145deg,#332b26,#665144) 会员价格卡 */
    val vipCard = ClLinearGradient(145f, listOf(Color(0xFF332B26), Color(0xFF665144)))

    /** linear-gradient(95deg,#7550e0,#a765f3) 等级徽章 */
    val level = ClLinearGradient(95f, listOf(Color(0xFF7550E0), Color(0xFFA765F3)))

    /** linear-gradient(110deg,#ffecd8,#ffe7c1) 「我的」页钱包卡 */
    val perk = ClLinearGradient(110f, listOf(Color(0xFFFFECD8), Color(0xFFFFE7C1)))
}

/** 一个 CSS 线性渐变：角度（0deg = 向上，顺时针）+ 颜色。 */
data class ClLinearGradient(
    val angleDeg: Float,
    val colors: List<Color>,
)

private const val TOP_WASH_YELLOW = 0xFFFFF7CB
private const val TOP_WASH_PINK = 0xFFFFF0F0
private const val TOP_WASH_BASE_TOP = 0xFFFFFAF2
private const val TOP_WASH_BASE_BOTTOM = 0xFFFFFFFF

/**
 * 画 CSS 角度的线性渐变。
 */
fun DrawScope.drawClGradient(gradient: ClLinearGradient) {
    val size = this.size
    val radians = Math.toRadians(gradient.angleDeg.toDouble())
    // CSS: 0deg 指向上方，顺时针为正；屏幕坐标 y 向下，故 dy 取负
    val dx = sin(radians).toFloat()
    val dy = -cos(radians).toFloat()
    // 让渐变线覆盖整个矩形
    val half = (size.width * abs(dx) + size.height * abs(dy)) / 2f
    val center = Offset(size.width / 2f, size.height / 2f)
    drawRect(
        brush = Brush.linearGradient(
            colors = gradient.colors,
            start = Offset(center.x - dx * half, center.y - dy * half),
            end = Offset(center.x + dx * half, center.y + dy * half),
        ),
        size = size,
    )
}

/**
 * 一级页面顶部背景（docs/03 第 2 节 gradientTopWash），对应原型 `.topwash`：
 *
 * `radial-gradient(circle at 8% 1%, #fff7cb 0, transparent 37%)`,
 * `radial-gradient(circle at 100% 45%, #fff0f0 0, transparent 38%)`,
 * `linear-gradient(#fffaf2, #fff 90%)`
 *
 * CSS 里靠前的图层在上，所以先画底层（线性），再依次画两块径向。
 */
fun DrawScope.drawClTopWash() {
    val size = this.size
    // 底层：linear-gradient(#fffaf2, #fff 90%)
    drawRect(
        brush = Brush.verticalGradient(
            colors = listOf(Color(TOP_WASH_BASE_TOP), Color(TOP_WASH_BASE_BOTTOM)),
            startY = 0f,
            endY = size.height * 0.9f,
        ),
        size = size,
    )
    // 右侧粉光：circle at 100% 45%, #fff0f0, transparent 38%
    drawCircleGradient(size, cx = 1.00f, cy = 0.45f, stop = 0.38f, color = Color(TOP_WASH_PINK))
    // 左上黄光：circle at 8% 1%, #fff7cb, transparent 37%
    drawCircleGradient(size, cx = 0.08f, cy = 0.01f, stop = 0.37f, color = Color(TOP_WASH_YELLOW))
}

/**
 * CSS `radial-gradient(circle at cx cy, color 0, transparent stop)`：
 * circle 的半径默认按 farthest-corner 计算，stop 是它的百分比。
 */
private fun DrawScope.drawCircleGradient(size: Size, cx: Float, cy: Float, stop: Float, color: Color) {
    val centerPx = Offset(size.width * cx, size.height * cy)
    val farthestCorner = hypot(
        max(cx, 1f - cx) * size.width,
        max(cy, 1f - cy) * size.height,
    )
    drawRect(
        brush = Brush.radialGradient(
            colors = listOf(color, Color.Transparent),
            center = centerPx,
            radius = farthestCorner * stop,
        ),
        size = size,
    )
}
