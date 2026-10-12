package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.UpdatePhotosRequestDto
import com.qichuang.changliao.core.network.dto.UpdateProfileRequestDto
import com.qichuang.changliao.core.network.dto.UpdateSettingsRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path

/**
 * App 用户资料接口（docs/05 第 4.2 节）。四个接口都要登录（`AuthInterceptor` 会带上 token）。
 *
 * `data` 统一声明成 [JsonElement]，由 `safeApiCall<T>` 按调用方要的类型解析。
 */
interface UserApi {

    @GET("api/v1/user/me")
    suspend fun me(): ApiResponse<JsonElement?>

    /** 他人主页（docs/05 第 4.2 节）。会顺便记一条访客。 */
    @GET("api/v1/user/{id}")
    suspend fun detail(@Path("id") id: Long): ApiResponse<JsonElement?>

    @POST("api/v1/user/profile")
    suspend fun updateProfile(@Body body: UpdateProfileRequestDto): ApiResponse<JsonElement?>

    @POST("api/v1/user/photos")
    suspend fun updatePhotos(@Body body: UpdatePhotosRequestDto): ApiResponse<JsonElement?>

    @POST("api/v1/user/settings")
    suspend fun updateSettings(@Body body: UpdateSettingsRequestDto): ApiResponse<JsonElement?>
}
