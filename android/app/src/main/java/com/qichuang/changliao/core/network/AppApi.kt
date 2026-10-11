package com.qichuang.changliao.core.network

import kotlinx.serialization.json.JsonElement
import retrofit2.http.GET
import retrofit2.http.Path

/**
 * App 启动配置与协议文本（docs/05 第 4.1 节）。这两个接口免鉴权。
 */
interface AppApi {

    @GET("api/v1/app/config")
    suspend fun config(): ApiResponse<JsonElement?>

    @GET("api/v1/app/agreement/{type}")
    suspend fun agreement(@Path("type") type: String): ApiResponse<JsonElement?>
}
