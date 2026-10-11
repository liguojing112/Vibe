package com.qichuang.changliao.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * 认证相关 DTO（docs/05 第 4.1 节）。字段名严格按服务端的 snake_case。
 */

@Serializable
data class RegisterRequestDto(
    val phone: String,
    val password: String,
    @SerialName("confirm_password") val confirmPassword: String? = null,
    val gender: Int,
    val nickname: String,
)

@Serializable
data class LoginRequestDto(
    val account: String,
    val password: String,
    @SerialName("device_id") val deviceId: String? = null,
)

@Serializable
data class RefreshRequestDto(
    @SerialName("refresh_token") val refreshToken: String,
)

@Serializable
data class ChangePasswordRequestDto(
    @SerialName("old_password") val oldPassword: String,
    @SerialName("new_password") val newPassword: String,
)

@Serializable
data class TokenDto(
    @SerialName("access_token") val accessToken: String,
    @SerialName("refresh_token") val refreshToken: String,
    @SerialName("expire_at") val expireAt: Int = 0,
)
