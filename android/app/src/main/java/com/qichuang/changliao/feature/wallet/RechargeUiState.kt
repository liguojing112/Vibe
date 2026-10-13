package com.qichuang.changliao.feature.wallet

import com.qichuang.changliao.core.data.model.RechargeInfo
import com.qichuang.changliao.core.data.model.RechargePack

/**
 * 充值页（P22）状态。
 *
 * **App 里没有支付**（docs/06 第 8 节：充值由后台客服人工完成），所以这个页面只有「选中档位」，
 * 没有任何下单/付款动作 —— 档位网格是给用户看着联系客服用的。
 */
data class RechargeUiState(
    val info: RechargeInfo? = null,
    val selectedPack: RechargePack? = null,
    val loading: Boolean = true,
    val error: String? = null,
    val toast: String? = null,
) {
    val balance: Long get() = info?.balance ?: 0

    /** 「本次到账」那一行；没选档位时显示占位。 */
    val selectedCoinsText: String
        get() = selectedPack?.let { "${it.coins} 金币" } ?: "请选择档位"

    /** 客服微信没配时不给复制按钮（复制一个空字符串没意义）。 */
    val canCopyWechat: Boolean get() = info?.service?.wechat?.isNotBlank() == true
}
