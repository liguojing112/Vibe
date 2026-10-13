package com.qichuang.changliao.core.network

import kotlinx.serialization.json.JsonElement
import retrofit2.http.GET
import retrofit2.http.Query

/**
 * 女用户的收益与等级（docs/05 第 4.8 节，P26/P27）。三个都是只读接口。
 */
interface IncomeApi {

    @GET("api/v1/income/summary")
    suspend fun summary(): ApiResponse<JsonElement?>

    @GET("api/v1/income/logs")
    suspend fun logs(@Query("page") page: Int): ApiResponse<JsonElement?>

    @GET("api/v1/income/level")
    suspend fun level(): ApiResponse<JsonElement?>
}
