package com.qichuang.changliao.core.common

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test
import java.time.LocalDateTime
import java.time.ZoneId

/**
 * 时间文案规则（docs/03 第 6 节第 5 条）：今天 `HH:mm`、昨天「昨天 HH:mm」、今年 `MM-dd HH:mm`、更早 `yyyy-MM-dd`。
 */
class TimeFormatTest {

    private val zone: ZoneId = ZoneId.of("Asia/Shanghai")

    /** 2026-10-12 15:30，用作「现在」。 */
    private val now = at(2026, 10, 12, 15, 30)

    private fun at(year: Int, month: Int, day: Int, hour: Int, minute: Int): Long =
        LocalDateTime.of(year, month, day, hour, minute).atZone(zone).toEpochSecond()

    @Test
    fun `今天只显示时分`() {
        assertEquals("15:30", TimeFormat.friendly(now, now, zone))
        assertEquals("00:00", TimeFormat.friendly(at(2026, 10, 12, 0, 0), now, zone))
        assertEquals("23:59", TimeFormat.friendly(at(2026, 10, 12, 23, 59), now, zone))
    }

    @Test
    fun `昨天加上昨天前缀`() {
        assertEquals("昨天 23:59", TimeFormat.friendly(at(2026, 10, 11, 23, 59), now, zone))
        assertEquals("昨天 09:05", TimeFormat.friendly(at(2026, 10, 11, 9, 5), now, zone))
    }

    @Test
    fun `昨天按日历天算不是按 24 小时算`() {
        // 距今 1 小时 1 分钟，但跨天了 → 昨天
        val justOverAnHourAgo = at(2026, 10, 11, 23, 59)
        assertEquals("昨天 23:59", TimeFormat.friendly(justOverAnHourAgo, now, zone))

        // 距今 15 小时，还是同一天 → 时分
        assertEquals("00:30", TimeFormat.friendly(at(2026, 10, 12, 0, 30), now, zone))
    }

    @Test
    fun `今年更早的显示月日时分`() {
        assertEquals("10-10 08:00", TimeFormat.friendly(at(2026, 10, 10, 8, 0), now, zone))
        assertEquals("01-01 00:00", TimeFormat.friendly(at(2026, 1, 1, 0, 0), now, zone))
    }

    @Test
    fun `去年及更早显示年月日`() {
        assertEquals("2025-12-31", TimeFormat.friendly(at(2025, 12, 31, 23, 59), now, zone))
        assertEquals("2020-05-06", TimeFormat.friendly(at(2020, 5, 6, 12, 0), now, zone))
    }

    @Test
    fun `跨年边界`() {
        // 去年的 12-31 和今年的 01-01 只差一天，但显示规则完全不同
        assertEquals("2025-12-31", TimeFormat.friendly(at(2025, 12, 31, 0, 0), now, zone))
        assertEquals("01-01 00:00", TimeFormat.friendly(at(2026, 1, 1, 0, 0), now, zone))
    }

    @Test
    fun `闰日能正常显示`() {
        val leapNow = at(2028, 2, 29, 12, 0)
        assertEquals("11:00", TimeFormat.friendly(at(2028, 2, 29, 11, 0), leapNow, zone))
        assertEquals("昨天 12:00", TimeFormat.friendly(at(2028, 2, 28, 12, 0), leapNow, zone))
        assertEquals("02-27 12:00", TimeFormat.friendly(at(2028, 2, 27, 12, 0), leapNow, zone))
    }

    @Test
    fun `非法时间戳返回空串`() {
        assertEquals("", TimeFormat.friendly(0, now, zone))
        assertEquals("", TimeFormat.friendly(-1, now, zone))
        assertEquals("", TimeFormat.dateTime(0, zone))
        assertEquals("", TimeFormat.date(0, zone))
    }

    @Test
    fun `时区参数生效`() {
        // 同一时间戳：上海 15:30，UTC 07:30
        assertEquals("15:30", TimeFormat.friendly(now, now, zone))
        assertEquals("07:30", TimeFormat.friendly(now, now, ZoneId.of("UTC")))
    }

    @Test
    fun `完整时间与日期格式`() {
        assertEquals("2026-10-12 15:30:00", TimeFormat.dateTime(now, zone))
        assertEquals("2026-10-12", TimeFormat.date(now, zone))
    }

    @Test
    fun `解析服务端时间能对上`() {
        assertEquals(now, TimeFormat.parse("2026-10-12 15:30:00", zone))
        assertEquals(now, TimeFormat.parse("  2026-10-12 15:30:00 ", zone))
    }

    @Test
    fun `解析不了的时间返回 null`() {
        assertNull(TimeFormat.parse("", zone))
        assertNull(TimeFormat.parse("2026/10/12 15:30:00", zone))
        assertNull(TimeFormat.parse("2026-10-12", zone))
        assertNull(TimeFormat.parse("2026-13-40 25:70:00", zone))
    }
}
