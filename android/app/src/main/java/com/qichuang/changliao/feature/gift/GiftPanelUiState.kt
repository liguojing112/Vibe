package com.qichuang.changliao.feature.gift

import com.qichuang.changliao.core.common.MoneyFormat
import com.qichuang.changliao.core.data.model.CoinNotEnough
import com.qichuang.changliao.core.data.model.Gift

/**
 * 礼物面板（P23 整页 / P31 聊天页弹窗）状态。两个页面用的是**同一份状态和同一套组件**。
 *
 * 礼物列表和余额都不做本地缓存：礼物是后台随时可调的运营数据（上下架、改价），
 * 余额是钱 —— 打开面板拉一次实时的才对。
 */
data class GiftPanelUiState(
    val receiverId: Long = 0,
    val gifts: List<Gift> = emptyList(),
    val selectedId: Long? = null,
    val balance: Long = 0,
    val loading: Boolean = true,
    val error: String? = null,
    val sending: Boolean = false,
    /** 余额不足弹 P30（和发私信共用那份弹窗）。 */
    val coinNotEnough: CoinNotEnough? = null,
    val toast: String? = null,
    /** 成功送出的次数：宿主页面（聊天页）用它触发一次「刷新收费条」。 */
    val sentCount: Long = 0,
) {

    val selected: Gift? get() = gifts.firstOrNull { it.id == selectedId }

    /** 底部按钮文案（设计图 23/31 的「赠送一朵玫瑰 · 10 金币」）。 */
    val buttonText: String
        get() = selected?.let { "赠送${it.name} · ${it.priceCoins} 金币" } ?: "请选择礼物"

    val balanceText: String get() = "可用余额 ${MoneyFormat.coins(balance)}"

    val isEmpty: Boolean get() = !loading && error == null && gifts.isEmpty()

    /** 没选礼物或正在送的时候按钮不可点。 */
    val canSend: Boolean get() = selected != null && !sending
}
