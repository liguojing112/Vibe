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

    /** 卡片按钮上挤不下「分钟」，用这个简写（`60 币/分`，设计图 01）。 */
    const val UNIT_PER_MINUTE_SHORT = "分"

    /** 金币的单位字（`ClCoinPill(amount, unit)` 要分开传）。 */
    const val UNIT_COIN = "币"

    /** 积分没有简写，单位就是「积分」。 */
    const val UNIT_POINT = "积分"

    /** 「120 金币」 */
    fun coins(value: Long): String = "$value 金币"

    /** 「120 币」—— 已经有「余额」「已扣」这类字样的紧凑位置用。 */
    fun coinsShort(value: Long): String = "$value 币"

    /** 「120 积分」 */
    fun points(value: Long): String = "$value 积分"

    /** 「5 币/条」「60 币/分钟」。 */
    fun coinPrice(value: Long, unit: String): String = "$value 币/$unit"

    /**
     * 单价**区间**：「5~20 币/条」。私信是按对方等级计价的，P13 上要显示区间。
     * 两端相等时退化成单价（「5 币/条」），免得出现「5~5 币/条」。
     */
    fun coinPriceRange(min: Long, max: Long, unit: String): String =
        if (min == max) coinPrice(min, unit) else "$min~$max 币/$unit"

    /** 流水里的增减：「+15」「-5」。 */
    fun signed(value: Long): String = if (value > 0) "+$value" else value.toString()

    /** 分 → 「¥12.50」；负数写成「-¥12.50」。 */
    fun rmb(fen: Long): String {
        val sign = if (fen < 0) "-" else ""
        val absolute = abs(fen)
        val cents = (absolute % 100).toString().padStart(2, '0')
        return "$sign¥${absolute / 100}.$cents"
    }

    /**
     * 整元 → 「¥39」。会员套餐的价格服务端给的本来就是整元（`price_yuan`），
     * 设计图 12 的横幅也是这么写的，所以不套 [rmb] 的小数位。
     */
    fun yuan(value: Long): String = "¥$value"

    /**
     * 万分比 → 「40%」/「34.5%」。
     *
     * 分成比例、手续费在服务端都是**万分比**（4000 = 40%），
     * 这里只做显示换算，不算钱（铁律 6）。
     */
    fun percent(rate: Int): String {
        val whole = rate / 100
        val decimal = rate % 100

        return if (decimal == 0) "$whole%" else "$whole.${decimal.toString().padStart(2, '0').trimEnd('0')}%"
    }
}
