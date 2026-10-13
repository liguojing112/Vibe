package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.VipBuyRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/**
 * 会员中心（docs/05 第 4.9 节，P12）。**只给男用户**，女用户 App 里没有这个入口。
 */
interface VipApi {

    @GET("api/v1/vip/info")
    suspend fun info(): ApiResponse<JsonElement?>

    @POST("api/v1/vip/buy")
    suspend fun buy(@Body body: VipBuyRequestDto): ApiResponse<JsonElement?>
}
