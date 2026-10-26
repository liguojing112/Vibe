package com.qichuang.changliao.feature.wallet

import com.qichuang.changliao.core.data.model.RechargeInfo
import com.qichuang.changliao.core.data.model.RechargeOrder
import com.qichuang.changliao.core.data.model.RechargePack

/**
 * 充值页（P22）状态。
 *
 * 客服充值仍然保留；后台配好商户号并打开开关后，[payEnabled] 为 true，
 * 页面才会出现「立即支付」，下单成功后 [payOrder] 驱动二维码弹窗。
 */
data class RechargeUiState(
    val info: RechargeInfo? = null,
    val selectedPack: RechargePack? = null,
    val loading: Boolean = true,
    val error: String? = null,
    val toast: String? = null,
    /** 正在创建订单（按钮转圈，防连点重复下单）。 */
    val paying: Boolean = false,
    /** 当前待支付的订单；不为空时显示二维码弹窗。 */
    val payOrder: RechargeOrder? = null,
) {
    val balance: Long get() = info?.balance ?: 0

    /** 「本次到账」那一行；没选档位时显示占位。 */
    val selectedCoinsText: String
        get() = selectedPack?.let { "${it.coins} 金币" } ?: "请选择档位"

    /** 客服微信没配时不给复制按钮（复制一个空字符串没意义）。 */
    val canCopyWechat: Boolean get() = info?.service?.wechat?.isNotBlank() == true

    /** 在线支付可用且选了档位，才显示「立即支付」。 */
    val canPay: Boolean get() = info?.payEnabled == true && selectedPack != null

    /** 按钮上的金额；金额也来自服务端档位，App 不参与定价。 */
    val payAmountText: String
        get() = selectedPack?.let { "¥${it.yuan}.00" } ?: ""
}
