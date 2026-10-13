package com.qichuang.changliao.core.designsystem.component

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil3.compose.AsyncImage
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 二维码边长（组件内部的默认值；要改就给 [ClQrCode] 传 size）。 */
private val QR_SIZE = 140.dp

/**
 * 客服二维码（docs/01 P22 的「充值方式」）。
 *
 * 为什么单独做一个：二维码**必须白底方形**（`ClAvatar` 是圆形、`ClImagePreview` 是全屏预览），
 * 而且后台没配二维码时调用方直接不显示这个组件。留白是刻意的 —— 二维码贴着彩色背景会扫不出来。
 */
@Composable
fun ClQrCode(
    url: String,
    modifier: Modifier = Modifier,
    size: Dp = QR_SIZE,
    contentDescription: String? = "客服二维码",
) {
    Box(
        modifier = modifier
            .size(size)
            .clip(ClTheme.shapes.cardSmall)
            .background(ClTheme.colors.surface),
    ) {
        AsyncImage(
            model = url,
            contentDescription = contentDescription,
            contentScale = ContentScale.Fit,
            modifier = Modifier.fillMaxSize(),
        )
    }
}
