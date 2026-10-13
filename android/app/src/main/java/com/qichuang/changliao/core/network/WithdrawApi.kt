package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.WithdrawApplyRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

/**
 * 提现（docs/05 第 4.8 节，P28）。只有女用户有；审核是后台的事。
 */
interface WithdrawApi {

    @GET("api/v1/withdraw/info")
    suspend fun info(): ApiResponse<JsonElement?>

    @POST("api/v1/withdraw/apply")
    suspend fun apply(@Body body: WithdrawApplyRequestDto): ApiResponse<JsonElement?>

    @GET("api/v1/withdraw/records")
    suspend fun records(@Query("page") page: Int): ApiResponse<JsonElement?>
}
