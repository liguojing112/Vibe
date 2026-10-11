package com.qichuang.changliao.core.common

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * 金额文案（docs/03 第 6 节第 5 条）：金币「金币」、价格里简写「币」、人民币两位小数。
 */
class MoneyFormatTest {

    @Test
    fun `金币用全称`() {
        assertEquals("120 金币", MoneyFormat.coins(120))
        assertEquals("0 金币", MoneyFormat.coins(0))
        assertEquals("999999 金币", MoneyFormat.coins(999999))
    }

    @Test
    fun `紧凑位置用简写`() {
        assertEquals("120 币", MoneyFormat.coinsShort(120))
    }

    @Test
    fun `积分用积分`() {
        assertEquals("15 积分", MoneyFormat.points(15))
        assertEquals("0 积分", MoneyFormat.points(0))
    }

    @Test
    fun `价格带单位`() {
        assertEquals("5 币/条", MoneyFormat.coinPrice(5, MoneyFormat.UNIT_PER_MESSAGE))
        assertEquals("60 币/分钟", MoneyFormat.coinPrice(60, MoneyFormat.UNIT_PER_MINUTE))
        assertEquals("0 币/条", MoneyFormat.coinPrice(0, MoneyFormat.UNIT_PER_MESSAGE))
    }

    @Test
    fun `流水增减带符号`() {
        assertEquals("+15", MoneyFormat.signed(15))
        assertEquals("-5", MoneyFormat.signed(-5))
        assertEquals("0", MoneyFormat.signed(0))
    }

    @Test
    fun `人民币永远是两位小数`() {
        assertEquals("¥12.50", MoneyFormat.rmb(1250))
        assertEquals("¥1.00", MoneyFormat.rmb(100))
        assertEquals("¥0.05", MoneyFormat.rmb(5))
        assertEquals("¥0.00", MoneyFormat.rmb(0))
        assertEquals("¥0.90", MoneyFormat.rmb(90))
        assertEquals("¥1000.00", MoneyFormat.rmb(100000))
    }

    @Test
    fun `人民币负数把符号放前面`() {
        assertEquals("-¥12.50", MoneyFormat.rmb(-1250))
        assertEquals("-¥0.01", MoneyFormat.rmb(-1))
    }
}
