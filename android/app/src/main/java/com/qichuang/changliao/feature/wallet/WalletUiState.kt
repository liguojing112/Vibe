package com.qichuang.changliao.feature.wallet

import com.qichuang.changliao.core.data.model.WalletCoin

/**
 * 金币钱包（P13）状态。
 *
 * 余额**每次进页面都重新拉**，不做本地缓存：这是钱，缓存一份旧余额比多等半秒危险得多
 * （docs/10 第 3 节列的「静默刷新 + 持久化」也不包含钱包）。
 */
data class WalletUiState(
    val coin: WalletCoin? = null,
    val loading: Boolean = true,
    val error: String? = null,
) {
    val balance: Long get() = coin?.balance ?: 0

    /** 余额卡下面的说明；语音/视频开关关着时就不提它们。 */
    val balanceDesc: String
        get() = if (coin?.callEnabled == true) "用于私信、语音、视频通话与礼物" else "用于私信与礼物"
}
