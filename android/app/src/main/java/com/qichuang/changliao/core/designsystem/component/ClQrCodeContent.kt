package com.qichuang.changliao.core.designsystem.component

import android.graphics.Bitmap
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/** 支付二维码在弹窗里的默认边长。 */
private val PAY_QR_SIZE = 196.dp

/** 生成位图的分辨率：比显示尺寸大，保证扫码识别率。 */
private const val BITMAP_SIZE = 720

/**
 * 按文本内容在本地生成二维码（P22 在线支付）。
 *
 * 为什么不用 [ClQrCode]：那个组件是 `AsyncImage`，只吃图片 URL；码支付给的是支付宝付款链接，
 * 必须用 zxing 自己画，**不能跳去码支付网站加载二维码**（经理明确要求）。
 */
@Composable
fun ClQrCodeContent(
    content: String,
    modifier: Modifier = Modifier,
    size: Dp = PAY_QR_SIZE,
) {
    val bitmap = remember(content) { generateQrBitmap(content) }

    Box(
        modifier = modifier
            .size(size)
            .clip(ClTheme.shapes.cardSmall)
            .background(ClTheme.colors.surface),
        contentAlignment = Alignment.Center,
    ) {
        if (bitmap != null) {
            Image(
                bitmap = bitmap.asImageBitmap(),
                contentDescription = "支付二维码",
                contentScale = ContentScale.Fit,
                modifier = Modifier
                    .fillMaxSize()
                    .padding(ClTheme.spacing.xs),
            )
        }
        else {
            Text(
                text = "二维码生成失败，请点下方按钮打开支付宝",
                style = ClTheme.typography.tiny,
                color = ClTheme.colors.muted,
            )
        }
    }
}

/** 生成白底黑块二维码；内容为空或编码异常时返回 null，由调用方兜底。 */
private fun generateQrBitmap(content: String): Bitmap? {
    if (content.isBlank()) {
        return null
    }

    return try {
        val matrix = QRCodeWriter().encode(
            content,
            BarcodeFormat.QR_CODE,
            BITMAP_SIZE,
            BITMAP_SIZE,
            mapOf(
                EncodeHintType.MARGIN to 2,
                EncodeHintType.CHARACTER_SET to "UTF-8",
            ),
        )

        val pixels = IntArray(BITMAP_SIZE * BITMAP_SIZE)
        for (y in 0 until BITMAP_SIZE) {
            val offset = y * BITMAP_SIZE
            for (x in 0 until BITMAP_SIZE) {
                pixels[offset + x] = if (matrix[x, y]) {
                    0xFF000000.toInt()
                }
                else {
                    0xFFFFFFFF.toInt()
                }
            }
        }

        Bitmap.createBitmap(BITMAP_SIZE, BITMAP_SIZE, Bitmap.Config.ARGB_8888).apply {
            setPixels(pixels, 0, BITMAP_SIZE, 0, 0, BITMAP_SIZE, BITMAP_SIZE)
        }
    }
    catch (_: Exception) {
        null
    }
}
