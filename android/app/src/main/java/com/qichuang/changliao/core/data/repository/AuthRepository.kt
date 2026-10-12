package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.Agreement
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.AppApi
import com.qichuang.changliao.core.network.AuthApi
import com.qichuang.changliao.core.network.TokenStore
import com.qichuang.changliao.core.network.dto.LoginRequestDto
import com.qichuang.changliao.core.network.dto.RegisterRequestDto
import com.qichuang.changliao.core.network.dto.TokenDto
import com.qichuang.changliao.core.network.safeApiCall
import javax.inject.Inject
import javax.inject.Singleton

/**
 * 认证与协议（docs/05 第 4.1 节）。
 *
 * **登录 / 注册成功就地把 token 存下来**，页面只管跳转 —— 别让每个页面都记得存 token。
 * 业务错误（10001/10002/10003/10004/10005）的提示语直接用服务端返回的 `message`（docs/03 第 6 节第 5 条）。
 */
@Singleton
class AuthRepository @Inject constructor(
    private val authApi: AuthApi,
    private val appApi: AppApi,
    private val tokenStore: TokenStore,
) {

    fun isLoggedIn(): Boolean = tokenStore.accessToken() != null

    suspend fun login(
        account: String,
        password: String,
        deviceId: String? = null,
    ): ApiResult<Unit> = saveTokensOnSuccess(
        safeApiCall<TokenDto> {
            authApi.login(LoginRequestDto(account = account, password = password, deviceId = deviceId))
        },
    )

    suspend fun register(
        phone: String,
        password: String,
        confirmPassword: String,
        gender: Int,
        nickname: String,
    ): ApiResult<Unit> = saveTokensOnSuccess(
        safeApiCall<TokenDto> {
            authApi.register(
                RegisterRequestDto(
                    phone = phone,
                    password = password,
                    confirmPassword = confirmPassword,
                    gender = gender,
                    nickname = nickname,
                ),
            )
        },
    )

    suspend fun agreement(type: String): ApiResult<Agreement> =
        safeApiCall<Agreement> { appApi.agreement(type) }

    /** 退出登录：服务端把 token 拉黑，本地无论如何都要清掉。 */
    suspend fun logout() {
        runCatching { authApi.logout() }
        tokenStore.clear()
    }

    private fun saveTokensOnSuccess(result: ApiResult<TokenDto>): ApiResult<Unit> = when (result) {
        is ApiResult.Success -> {
            tokenStore.saveTokens(result.data.accessToken, result.data.refreshToken)
            ApiResult.Success(Unit)
        }

        is ApiResult.BizError -> result
        is ApiResult.NetworkError -> result
    }
}
