package com.qichuang.changliao.core.common

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.ByteArrayOutputStream
import java.io.IOException
import kotlin.math.max
import kotlin.math.roundToInt

/**
 * 上传前压缩（docs/02 第 4.4 节）：长边不超过 1080px、JPEG 质量 80。
 *
 * 同时把手机拍照带来的 EXIF 旋转**扳正**，否则后台和聊天里看到的人像是躺着的。
 */
object ImageCompressor {

    /** 长边上限（px）。 */
    const val MAX_LONG_SIDE = 1080

    /** JPEG 质量。 */
    const val QUALITY = 80

    /** 读图 → 按需降采样 → 扳正方向 → 精确缩放到长边 1080 → JPEG。 */
    suspend fun compress(context: Context, uri: Uri): ByteArray = withContext(Dispatchers.IO) {
        val (width, height) = readBounds(context, uri)

        val options = BitmapFactory.Options().apply {
            inSampleSize = calculateInSampleSize(width, height, MAX_LONG_SIDE)
            inPreferredConfig = Bitmap.Config.ARGB_8888
        }
        val decoded = context.contentResolver.openInputStream(uri)?.use {
            BitmapFactory.decodeStream(it, null, options)
        } ?: throw IOException("图片读取失败")

        val upright = applyExifRotation(context, uri, decoded)
        val (targetWidth, targetHeight) = calculateTargetSize(upright.width, upright.height, MAX_LONG_SIDE)
        val scaled = if (targetWidth != upright.width || targetHeight != upright.height) {
            Bitmap.createScaledBitmap(upright, targetWidth, targetHeight, true)
        } else {
            upright
        }

        val bytes = ByteArrayOutputStream().use { output ->
            scaled.compress(Bitmap.CompressFormat.JPEG, QUALITY, output)
            output.toByteArray()
        }

        if (scaled !== upright) {
            scaled.recycle()
        }
        if (upright !== decoded) {
            upright.recycle()
        }
        decoded.recycle()

        bytes
    }

    /**
     * 降采样倍数取 2 的幂，保证 `长边 / 倍数 <= maxLongSide`。
     * 例：4000×3000 → 4（得 1000×750）。
     */
    internal fun calculateInSampleSize(width: Int, height: Int, maxLongSide: Int): Int {
        if (maxLongSide <= 0 || width <= 0 || height <= 0) {
            return 1
        }
        var sampleSize = 1
        while (max(width, height) / sampleSize > maxLongSide) {
            sampleSize *= 2
        }
        return sampleSize
    }

    /**
     * 精确缩放后的尺寸：长边等于 [maxLongSide]，短边按比例。
     * 本来就够小的图**不放大**（放大只会变糊）。
     */
    internal fun calculateTargetSize(width: Int, height: Int, maxLongSide: Int): Pair<Int, Int> {
        val longSide = max(width, height)
        if (maxLongSide <= 0 || longSide <= maxLongSide || longSide <= 0) {
            return width to height
        }
        val ratio = maxLongSide.toFloat() / longSide
        return max(1, (width * ratio).roundToInt()) to max(1, (height * ratio).roundToInt())
    }

    private fun readBounds(context: Context, uri: Uri): Pair<Int, Int> {
        val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
        context.contentResolver.openInputStream(uri)?.use {
            BitmapFactory.decodeStream(it, null, options)
        }
        if (options.outWidth <= 0 || options.outHeight <= 0) {
            throw IOException("图片读取失败")
        }
        return options.outWidth to options.outHeight
    }

    private fun applyExifRotation(context: Context, uri: Uri, source: Bitmap): Bitmap {
        val orientation = runCatching {
            context.contentResolver.openInputStream(uri)?.use { stream ->
                ExifInterface(stream).getAttributeInt(
                    ExifInterface.TAG_ORIENTATION,
                    ExifInterface.ORIENTATION_NORMAL,
                )
            } ?: ExifInterface.ORIENTATION_NORMAL
        }.getOrDefault(ExifInterface.ORIENTATION_NORMAL)

        val matrix = Matrix()
        when (orientation) {
            ExifInterface.ORIENTATION_ROTATE_90 -> matrix.postRotate(90f)
            ExifInterface.ORIENTATION_ROTATE_180 -> matrix.postRotate(180f)
            ExifInterface.ORIENTATION_ROTATE_270 -> matrix.postRotate(270f)
            ExifInterface.ORIENTATION_FLIP_HORIZONTAL -> matrix.postScale(-1f, 1f)
            ExifInterface.ORIENTATION_FLIP_VERTICAL -> matrix.postScale(1f, -1f)
            ExifInterface.ORIENTATION_TRANSPOSE -> {
                matrix.postRotate(90f)
                matrix.postScale(-1f, 1f)
            }

            ExifInterface.ORIENTATION_TRANSVERSE -> {
                matrix.postRotate(270f)
                matrix.postScale(-1f, 1f)
            }

            else -> return source
        }

        return Bitmap.createBitmap(source, 0, 0, source.width, source.height, matrix, true)
    }
}
