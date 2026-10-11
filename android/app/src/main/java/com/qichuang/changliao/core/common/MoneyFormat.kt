package com.qichuang.changliao.core.common

import kotlin.math.abs

/**
 * 金额与币值文案（docs/03 第 6 节第 5 条）：
 * 金币写「金币」、价格里的简写「币」、积分写「积分」、人民币写 `¥12.50`（两位小数）。
 *
 * 这里只负责**显示**：所有金额、价格都由服务端算好返回（铁律 6），App 不做任何换算。
 */
object MoneyFormat {

    /** 消息价格单位（`5 币/条`）。 */
    const val UNIT_PER_MESSAGE = "条"

    /** 通话价格单位（`60 币/分钟`）。 */
    const val UNIT_PER_MINUTE = "分钟"

    /** 「120 金币」 */
    fun coins(value: Long): String = "$value 金币"

    /** 「120 币」—— 已经有「余额」「已扣」这类字样的紧凑位置用。 */
    fun coinsShort(value: Long): String = "$value 币"

    /** 「120 积分」 */
    fun points(value: Long): String = "$value 积分"

    /** 「5 币/条」「60 币/分钟」。 */
    fun coinPrice(value: Long, unit: String): String = "$value 币/$unit"

    /** 流水里的增减：「+15」「-5」。 */
    fun signed(value: Long): String = if (value > 0) "+$value" else value.toString()

    /** 分 → 「¥12.50」；负数写成「-¥12.50」。 */
    fun rmb(fen: Long): String {
        val sign = if (fen < 0) "-" else ""
        val absolute = abs(fen)
        val cents = (absolute % 100).toString().padStart(2, '0')
        return "$sign¥${absolute / 100}.$cents"
    }
}
