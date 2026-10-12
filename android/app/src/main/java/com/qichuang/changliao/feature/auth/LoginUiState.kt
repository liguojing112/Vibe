package com.qichuang.changliao.feature.auth

/**
 * 登录页状态（P00）。错误文案直接挂在字段上，页面不用自己判断。
 *
 * [toast] 是一次性提示（业务错误的文案来自服务端）：页面显示完调 `onToastShown()` 清掉。
 */
data class LoginUiState(
    val account: String = "",
    val password: String = "",
    val agreed: Boolean = false,
    val accountError: String? = null,
    val passwordError: String? = null,
    val agreementError: String? = null,
    val submitting: Boolean = false,
    val toast: String? = null,
)
