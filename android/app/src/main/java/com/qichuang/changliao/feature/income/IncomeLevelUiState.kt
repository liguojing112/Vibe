package com.qichuang.changliao.feature.income

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.IncomeLevel

/**
 * 我的等级与收费（P27）状态。**整页只读**（价格由后台按等级统一定，本期不做自主定价）。
 *
 * 分成比例服务端给的是**万分比**（4000 = 40%），这里只做显示换算。
 */
data class IncomeLevelUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val level: IncomeLevel? = null,
    /** 「在线接听」开关：关掉就不出现在发现页的「推荐」里。 */
    val showInRecommend: Boolean = true,
    val switching: Boolean = false,
    val toast: String? = null,
) {

    val levelText: String get() = level?.name.orEmpty()

    val earnedText: String get() = MoneyFormat.points(level?.totalEarnedPoints ?: 0)

    /** 进度条用的 0~1（服务端给的是百分比）。 */
    val progress: Float get() = (level?.progressPercent ?: 0) / 100f

    /** 「距离 Lv3 还差 180000 积分」；满级就说满了。 */
    val progressText: String
        get() = when {
            level == null -> ""
            level.nextLevel == null -> "已经是最高等级"
            else -> "距离 ${level.name} 的下一级还差 ${MoneyFormat.points(level.pointsToNext)}"
        }

    /** 「10 金币/条」——私信单价按我的等级定。 */
    val messagePriceText: String
        get() = level?.let { MoneyFormat.coinPrice(it.messagePrice.toLong(), MoneyFormat.UNIT_PER_MESSAGE) }.orEmpty()

    /** 我**实际**能拿到的分成（未实名时是打折后的）。 */
    val shareText: String get() = level?.let { MoneyFormat.percent(it.actualShareRate) }.orEmpty()

    val giftShareText: String get() = level?.let { MoneyFormat.percent(it.giftShareRate) }.orEmpty()

    /**
     * 未实名时要按 `docs/01` P27 说明一句：
     * 「完成实名认证后分成比例为 X%，当前为 Y%」。
     */
    val shareHint: String
        get() = level?.let {
            if (it.realNamePassed) {
                ""
            } else {
                "完成实名认证后分成比例为 ${MoneyFormat.percent(it.shareRate)}，当前为 ${MoneyFormat.percent(it.actualShareRate)}"
            }
        }.orEmpty()

    val showRealNameHint: Boolean get() = level?.realNamePassed == false

    /** 语音/视频单价只在 `feature.call_enabled` 打开时才显示（和 P13 一个口径）。 */
    val callEnabled: Boolean get() = level?.callEnabled == true

    val voicePriceText: String
        get() = level?.let { MoneyFormat.coinPrice(it.voicePrice.toLong(), MoneyFormat.UNIT_PER_MINUTE) }.orEmpty()

    val videoPriceText: String
        get() = level?.let { MoneyFormat.coinPrice(it.videoPrice.toLong(), MoneyFormat.UNIT_PER_MINUTE) }.orEmpty()

    val isEmpty: Boolean get() = !loading && error == null && level == null
}
