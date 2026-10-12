package com.qichuang.changliao.core.data.model

import kotlinx.serialization.Serializable

/** 协议文本（docs/05 第 4.1 节 `GET /api/v1/app/agreement/{type}`，后台可编辑）。 */
@Serializable
data class Agreement(
    val type: String = "",
    val content: String = "",
) {
    companion object {
        /** 用户协议。 */
        const val TYPE_USER = "user"

        /** 隐私政策。 */
        const val TYPE_PRIVACY = "privacy"
    }
}
