package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.ChangePasswordRequestDto
import com.qichuang.changliao.core.network.dto.LoginRequestDto
import com.qichuang.changliao.core.network.dto.RefreshRequestDto
import com.qichuang.changliao.core.network.dto.RegisterRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.Headers
import retrofit2.http.POST

/**
 * App 认证接口（docs/05 第 4.1 节）。前缀统一在 Retrofit 的 baseUrl 里。
 *
 * `data` 统一声明成 [JsonElement]：成功和失败时结构不同（业务错误可能带别的字段），
 * 由 `safeApiCall<T>` 按调用方要的类型解析。
 */
interface AuthApi {

    @POST("api/v1/auth/register")
    suspend fun register(@Body body: RegisterRequestDto): ApiResponse<JsonElement?>

    @POST("api/v1/auth/login")
    suspend fun login(@Body body: LoginRequestDto): ApiResponse<JsonElement?>

    /** 刷新本身不做 401 自动刷新，否则会递归。 */
    @Headers("$HEADER_NO_AUTH: true")
    @POST("api/v1/auth/refresh")
    suspend fun refresh(@Body body: RefreshRequestDto): ApiResponse<JsonElement?>

    @POST("api/v1/auth/logout")
    suspend fun logout(): ApiResponse<JsonElement?>

    @POST("api/v1/auth/password")
    suspend fun changePassword(@Body body: ChangePasswordRequestDto): ApiResponse<JsonElement?>

    /** 注销账号（P15）。二次确认在客户端做。 */
    @POST("api/v1/auth/cancel")
    suspend fun cancel(): ApiResponse<JsonElement?>
}
