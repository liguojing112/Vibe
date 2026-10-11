package com.qichuang.changliao.core.designsystem.theme

import android.graphics.BlurMaskFilter
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.ReadOnlyComposable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Outline
import androidx.compose.ui.graphics.Paint
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.drawscope.drawIntoCanvas
import androidx.compose.ui.unit.LayoutDirection

/**
 * 卡片阴影颜色（docs/03 第 4 节写的是 `#F5F1EB`，不在颜色 token 表里，所以放在这里）。
 */
private val CardShadowColor = Color(0xFFF5F1EB)

private val LocalClColors = staticCompositionLocalOf { ClLightColors }
private val LocalClTypography = staticCompositionLocalOf { ClTextStyles }
private val LocalClSpacing = staticCompositionLocalOf { ClSpacings }
private val LocalClSize = staticCompositionLocalOf { ClSizes }
private val LocalClShapes = staticCompositionLocalOf { ClShapesDefault }
private val LocalClElevation = staticCompositionLocalOf { ClElevations }

/**
 * 设计系统入口（docs/03 第 1 节）。页面里只准用 `ClTheme.colors/spacing/size/shapes/typography`。
 */
object ClTheme {
    val colors: ClColors
        @Composable @ReadOnlyComposable get() = LocalClColors.current

    val typography: ClTypography
        @Composable @ReadOnlyComposable get() = LocalClTypography.current

    val spacing: ClSpacing
        @Composable @ReadOnlyComposable get() = LocalClSpacing.current

    val size: ClSize
        @Composable @ReadOnlyComposable get() = LocalClSize.current

    val shapes: ClShapes
        @Composable @ReadOnlyComposable get() = LocalClShapes.current

    val elevation: ClElevation
        @Composable @ReadOnlyComposable get() = LocalClElevation.current
}

/**
 * 强制浅色，不做深色模式。
 */
@Composable
fun ClTheme(content: @Composable () -> Unit) {
    CompositionLocalProvider(
        LocalClColors provides ClLightColors,
        LocalClTypography provides ClTextStyles,
        LocalClSpacing provides ClSpacings,
        LocalClSize provides ClSizes,
        LocalClShapes provides ClShapesDefault,
        LocalClElevation provides ClElevations,
    ) {
        content()
    }
}

/**
 * 统一卡片阴影（docs/03 第 4 节）：Y 偏移 5dp、模糊 20dp、颜色 #F5F1EB。
 * 全项目只有这一种卡片阴影，页面里不准自己写阴影。
 */
fun Modifier.clCardShadow(shape: Shape = ClShapesDefault.card): Modifier = drawBehind {
    val paint = Paint().apply {
        color = CardShadowColor
        asFrameworkPaint().apply {
            isAntiAlias = true
            maskFilter = BlurMaskFilter(ClElevations.cardBlur.toPx(), BlurMaskFilter.Blur.NORMAL)
        }
    }
    val outline = shape.createOutline(size, LayoutDirection.Ltr, this)
    val path = when (outline) {
        is Outline.Rounded -> Path().apply { addRoundRect(outline.roundRect) }
        is Outline.Rectangle -> Path().apply { addRect(outline.rect) }
        is Outline.Generic -> outline.path
    }
    drawIntoCanvas { canvas ->
        canvas.save()
        canvas.translate(0f, ClElevations.cardOffsetY.toPx())
        canvas.drawPath(path, paint)
        canvas.restore()
    }
}

/**
 * 一级页面顶部渐变背景（docs/03 第 2 节 gradientTopWash）。
 */
fun Modifier.clTopWashBackground(): Modifier = drawBehind { drawClTopWash() }

/**
 * 渐变背景（docs/03 第 2 节的其余渐变，见 [ClGradients]）。
 */
fun Modifier.clGradientBackground(gradient: ClLinearGradient): Modifier = drawBehind { drawClGradient(gradient) }
