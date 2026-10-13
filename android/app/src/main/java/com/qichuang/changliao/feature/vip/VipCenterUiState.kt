package com.qichuang.changliao.feature.vip

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.common.TimeFormat
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.data.model.VipBenefit
import com.qichuang.changliao.core.data.model.VipPlan

/**
 * 会员中心（P12）状态。
 *
 * 套餐、权益、提示卡的文案**全部来自服务端**（`docs/05` `/vip/info`），这里只负责挑一个套餐、
 * 拼底部按钮的文案 —— 价格一个字都不在客户端算（铁律 6）。
 */
data class VipCenterUiState(
    val loading: Boolean = true,
    val error: String? = null,
    val isVip: Boolean = false,
    /** 服务端的 `yyyy-MM-dd HH:mm:ss`；不是会员时是空串。 */
    val expireAt: String = "",
    val plans: List<VipPlan> = emptyList(),
    val benefits: List<VipBenefit> = emptyList(),
    val balance: Long = 0,
    /** 「会员与聊天费用」提示卡。 */
    val notice: String = "",
    val selectedId: Long? = null,
    val buying: Boolean = false,
    /** 余额不足弹 P30（和发私信、送礼物共用那份弹窗）。 */
    val coinNotEnough: CoinNotEnough? = null,
    val toast: String? = null,
    /** 成功开通的次数：宿主页面用它触发一次「到期时间刷新」（不算必须，但界面要立刻变）。 */
    val boughtCount: Long = 0,
) {

    val selected: VipPlan? get() = plans.firstOrNull { it.id == selectedId }

    /** 横幅右上角的「¥39」（设计图 12）。没选套餐时留空，不显示假价格。 */
    val priceYuan: String get() = selected?.let { MoneyFormat.yuan(it.priceYuan.toLong()) } ?: ""

    /** 「30 天」。 */
    val daysText: String get() = selected?.let { "${it.days} 天" } ?: ""

    /** 「有效期至 2026-12-12」；不是会员时是空串。 */
    val expireText: String
        get() = if (!isVip) {
            ""
        } else {
            TimeFormat.parse(expireAt)?.let { "有效期至 ${TimeFormat.date(it)}" } ?: ""
        }

    /** 横幅副标题：已是会员就把到期时间顶上来，否则放那句宣传语。 */
    val heroSubtitle: String get() = expireText.ifEmpty { "更高曝光 · 更多匹配机会 · 专属互动权益" }

    /** 底部按钮：已是会员写「续费」（`docs/01` P12 —— 续费到期时间顺延）。 */
    val buttonText: String
        get() = selected?.let {
            "${if (isVip) "续费" else "立即开通"} ${it.name} · ${MoneyFormat.coins(it.priceCoins.toLong())}"
        } ?: "请选择套餐"

    val balanceText: String get() = MoneyFormat.coins(balance)

    val isEmpty: Boolean get() = !loading && error == null && plans.isEmpty()

    val canBuy: Boolean get() = selected != null && !buying
}
