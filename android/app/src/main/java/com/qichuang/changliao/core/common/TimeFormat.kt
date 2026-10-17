package com.qichuang.changliao.core.common

import java.time.Instant
import java.time.LocalDate
import java.time.LocalDateTime
import java.time.ZoneId
import java.time.ZoneOffset
import java.time.format.DateTimeFormatter

/**
 * 时间格式化（docs/03 第 6 节第 5 条）：页面显示时间必须走这里，不要自己拼字符串。
 *
 * 显示规则：今天 `HH:mm`、昨天「昨天 HH:mm」、今年 `MM-dd HH:mm`、更早 `yyyy-MM-dd`。
 */
object TimeFormat {

    /** 服务端返回的时间格式（`created_at` 之类）。 */
    const val SERVER_PATTERN = "yyyy-MM-dd HH:mm:ss"

    /**
     * 服务端用的时区，**必须和 `server/.env` 的 `APP_TIMEZONE` 一致**。
     *
     * 服务端返回的是**不带时区**的裸字符串（`2026-10-17 23:38:04`），
     * 解析时必须按「它所在的时区」解释，不能用设备的时区 ——
     * 否则用户手机不在东八区时，消息时间会整体偏移（T9.8 在时区为 GMT 的模拟器上实测：
     * 成功消息的时间戳比失败消息晚 8 小时，失败气泡被排到列表最上面）。
     */
    val SERVER_ZONE: ZoneId = ZoneId.of("Asia/Shanghai")

    private val serverFormatter = DateTimeFormatter.ofPattern(SERVER_PATTERN)
    private val hourMinute = DateTimeFormatter.ofPattern("HH:mm")
    private val monthDayTime = DateTimeFormatter.ofPattern("MM-dd HH:mm")
    private val monthDay = DateTimeFormatter.ofPattern("MM-dd")
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

    /**
     * 只说到「哪一天」：今天 / 昨天 / `MM-dd` / `yyyy-MM-dd`。
     * P08、P09 的「今天看了你的资料 · 上海」这种句子用它（[friendly] 是带时分的，不适合）。
     */
    fun dayLabel(
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

        return when {
            date == today -> "今天"
            date == today.minusDays(1) -> "昨天"
            date.year == today.year -> at.format(monthDay)
            else -> at.format(yearMonthDay)
        }
    }

    /** 把服务端的 `yyyy-MM-dd HH:mm:ss` 转成秒级时间戳；解析不了返回 null。 */
    fun parse(
        text: String,
        zone: ZoneId = SERVER_ZONE,
    ): Long? = runCatching {
        LocalDateTime.parse(text.trim(), serverFormatter).atZone(zone).toEpochSecond()
    }.getOrNull()

    /**
     * 日期选择器给的 **UTC 零点毫秒** → `yyyy-MM-dd`。
     * Material3 的 `DatePicker` 用的是 UTC 零点，所以这里也必须按 UTC 取日期，否则会差一天。
     */
    fun utcMillisToDate(utcMillis: Long): String =
        Instant.ofEpochMilli(utcMillis).atZone(ZoneOffset.UTC).toLocalDate().toString()

    /** `yyyy-MM-dd` → 日期选择器要的 UTC 零点毫秒；解析不了返回 null。 */
    fun dateToUtcMillis(date: String): Long? = runCatching {
        LocalDate.parse(date.trim()).atStartOfDay(ZoneOffset.UTC).toInstant().toEpochMilli()
    }.getOrNull()
}
