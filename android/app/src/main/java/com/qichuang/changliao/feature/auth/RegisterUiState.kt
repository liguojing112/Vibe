package com.qichuang.changliao.feature.auth

/** 注册页状态（P00）。校验规则与 `RegisterRequest.php` 一致，错误文案来自 `core/common/Validators`。 */
data class RegisterUiState(
    val phone: String = "",
    val password: String = "",
    val confirmPassword: String = "",
    val gender: Int = 0,
    val nickname: String = "",
    val phoneError: String? = null,
    val passwordError: String? = null,
    val confirmPasswordError: String? = null,
    val genderError: String? = null,
    val nicknameError: String? = null,
    val submitting: Boolean = false,
    val toast: String? = null,
)
