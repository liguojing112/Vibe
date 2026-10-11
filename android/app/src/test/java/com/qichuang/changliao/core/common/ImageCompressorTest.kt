package com.qichuang.changliao.core.common

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * 压缩前的两个纯计算（docs/02 第 4.4 节：长边不超过 1080px）。
 *
 * 真正读图/编码那步依赖 Android 的 Bitmap，只能在设备上跑，这里覆盖它的参数计算。
 */
class ImageCompressorTest {

    @Test
    fun `降采样倍数取 2 的幂`() {
        assertEquals(4, ImageCompressor.calculateInSampleSize(4000, 3000, 1080))
        assertEquals(2, ImageCompressor.calculateInSampleSize(2160, 1920, 1080))
        assertEquals(2, ImageCompressor.calculateInSampleSize(1081, 1080, 1080))
        assertEquals(8, ImageCompressor.calculateInSampleSize(8000, 6000, 1080))
    }

    @Test
    fun `本来就够小的图不降采样`() {
        assertEquals(1, ImageCompressor.calculateInSampleSize(1080, 1080, 1080))
        assertEquals(1, ImageCompressor.calculateInSampleSize(800, 600, 1080))
        assertEquals(1, ImageCompressor.calculateInSampleSize(1, 1, 1080))
    }

    @Test
    fun `非法参数不降采样`() {
        assertEquals(1, ImageCompressor.calculateInSampleSize(0, 0, 1080))
        assertEquals(1, ImageCompressor.calculateInSampleSize(4000, 3000, 0))
        assertEquals(1, ImageCompressor.calculateInSampleSize(4000, 3000, -1))
    }

    @Test
    fun `横图长边缩到 1080`() {
        assertEquals(1080 to 810, ImageCompressor.calculateTargetSize(4000, 3000, 1080))
        assertEquals(1080 to 720, ImageCompressor.calculateTargetSize(1920, 1280, 1080))
    }

    @Test
    fun `竖图按高度缩`() {
        assertEquals(810 to 1080, ImageCompressor.calculateTargetSize(3000, 4000, 1080))
    }

    @Test
    fun `正方形`() {
        assertEquals(1080 to 1080, ImageCompressor.calculateTargetSize(2000, 2000, 1080))
    }

    @Test
    fun `本来就够小的图不放大`() {
        assertEquals(800 to 600, ImageCompressor.calculateTargetSize(800, 600, 1080))
        assertEquals(1080 to 1080, ImageCompressor.calculateTargetSize(1080, 1080, 1080))
        assertEquals(1 to 1, ImageCompressor.calculateTargetSize(1, 1, 1080))
    }

    @Test
    fun `极端长宽比也不会缩成 0`() {
        assertEquals(1 to 1080, ImageCompressor.calculateTargetSize(1, 10000, 1080))
        assertEquals(1080 to 1, ImageCompressor.calculateTargetSize(10000, 1, 1080))
    }

    @Test
    fun `非法参数原样返回`() {
        assertEquals(4000 to 3000, ImageCompressor.calculateTargetSize(4000, 3000, 0))
        assertEquals(4000 to 3000, ImageCompressor.calculateTargetSize(4000, 3000, -1))
    }
}
