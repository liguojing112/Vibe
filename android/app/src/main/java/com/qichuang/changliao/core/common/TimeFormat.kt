package com.qichuang.changliao.core.common

import java.time.Instant
import java.time.LocalDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/**
 * 时间格式化（docs/03 第 6 节第 5 条）：页面显示时间必须走这里，不要自己拼字符串。
 *
 * 显示规则：今天 `HH:mm`、昨天「昨天 HH:mm」、今年 `MM-dd HH:mm`、更早 `yyyy-MM-dd`。
 */
object TimeFormat {

    /** 服务端返回的时间格式（`created_at` 之类）。 */
    const val SERVER_PATTERN = "yyyy-MM-dd HH:mm:ss"

    private val serverFormatter = DateTimeFormatter.ofPattern(SERVER_PATTERN)
    private val hourMinute = DateTimeFormatter.ofPattern("HH:mm")
    private val monthDayTime = DateTimeFormatter.ofPattern("MM-dd HH:mm")
    private val yearMonthDay = DateTimeFormatter.ofPattern("yyyy-MM-dd")

    /**
     * 列表 / 聊天里的时间文案。时间戳非法（<= 0）时返回空串，让调用方显示占位。
     */
    fun friendly(
        epochSeconds: Long,
        nowSeconds: Long = System.currentTimeMillis() / 1000,
        zone: ZoneId = ZoneId.systemDefault(),
    ): String {
        if (epochSeconds <= 0) {
            return ""
        }
        val at = Instant.ofEpochSecond(epochSeconds).atZone(zone)
        val today = Instant.ofEpochSecond(nowSeconds).atZone(zone).toLocalDate()
        val date = at.toLocalDate()
        val time = at.toLocalTime().format(hourMinute)

        return when {
            date == today -> time
            date == today.minusDays(1) -> "昨天 $time"
            date.year == today.year -> at.format(monthDayTime)
            else -> at.format(yearMonthDay)
        }
    }

    /** `yyyy-MM-dd HH:mm:ss`，后台审核时间、明细页这类要完整时间的场景用。 */
    fun dateTime(epochSeconds: Long, zone: ZoneId = ZoneId.systemDefault()): String {
        if (epochSeconds <= 0) {
            return ""
        }
        return Instant.ofEpochSecond(epochSeconds).atZone(zone).format(serverFormatter)
    }

    /** `yyyy-MM-dd`。 */
    fun date(epochSeconds: Long, zone: ZoneId = ZoneId.systemDefault()): String {
        if (epochSeconds <= 0) {
            return ""
        }
        return Instant.ofEpochSecond(epochSeconds).atZone(zone).format(yearMonthDay)
    }

    /** 把服务端的 `yyyy-MM-dd HH:mm:ss` 转成秒级时间戳；解析不了返回 null。 */
    fun parse(
        text: String,
        zone: ZoneId = ZoneId.systemDefault(),
    ): Long? = runCatching {
        LocalDateTime.parse(text.trim(), serverFormatter).atZone(zone).toEpochSecond()
    }.getOrNull()
}
