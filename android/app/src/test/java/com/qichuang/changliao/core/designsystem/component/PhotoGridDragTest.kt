package com.qichuang.changliao.core.designsystem.component

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * 相册长按拖动的落点计算（docs/01 P14「可拖动排序」）。
 *
 * 这是拖动交互里唯一能脱离设备测的部分：没有它，拖动只能靠手试。
 */
class PhotoGridDragTest {

    /** 9 张照片，下标 0~8。 */
    private val lastIndex = 8

    @Test
    fun `没移动就留在原位`() {
        assertEquals(4, photoDragTarget(from = 4, columnDelta = 0, rowDelta = 0, lastIndex = lastIndex))
    }

    @Test
    fun `往右挪一格是后一个`() {
        assertEquals(5, photoDragTarget(from = 4, columnDelta = 1, rowDelta = 0, lastIndex = lastIndex))
    }

    @Test
    fun `往左挪一格是前一个`() {
        assertEquals(3, photoDragTarget(from = 4, columnDelta = -1, rowDelta = 0, lastIndex = lastIndex))
    }

    @Test
    fun `往下挪一格是后三个`() {
        assertEquals(7, photoDragTarget(from = 4, columnDelta = 0, rowDelta = 1, lastIndex = lastIndex))
    }

    @Test
    fun `往上挪一格是前三个`() {
        assertEquals(1, photoDragTarget(from = 4, columnDelta = 0, rowDelta = -1, lastIndex = lastIndex))
    }

    @Test
    fun `斜着拖是两者相加`() {
        // 第 3 张（下标 2）往右下挪一格 → 6
        assertEquals(6, photoDragTarget(from = 2, columnDelta = 1, rowDelta = 1, lastIndex = lastIndex))
    }

    @Test
    fun `拖出左下角贴在开头`() {
        assertEquals(0, photoDragTarget(from = 1, columnDelta = -5, rowDelta = -5, lastIndex = lastIndex))
    }

    @Test
    fun `拖出右下角贴在末尾`() {
        assertEquals(8, photoDragTarget(from = 7, columnDelta = 5, rowDelta = 5, lastIndex = lastIndex))
    }

    @Test
    fun `最后一行往右拖不会跳到下一行`() {
        // 下标 7 已经在最后一行的第一列，往右最多到 8（不能绕到 10）
        assertEquals(8, photoDragTarget(from = 7, columnDelta = 1, rowDelta = 0, lastIndex = lastIndex))
    }

    @Test
    fun `只有一张照片时拖不动`() {
        assertEquals(0, photoDragTarget(from = 0, columnDelta = 3, rowDelta = 3, lastIndex = 0))
    }
}
