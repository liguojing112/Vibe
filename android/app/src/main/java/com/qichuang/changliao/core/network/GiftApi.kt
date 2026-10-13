package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.GiftSendRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/** 礼物（docs/05 第 4.6 节）：列表 + 赠送。P23 与 P31 共用同一个列表。 */
interface GiftApi {

    @GET("api/v1/gift/list")
    suspend fun list(): ApiResponse<JsonElement?>

    @POST("api/v1/gift/send")
    suspend fun send(@Body body: GiftSendRequestDto): ApiResponse<JsonElement?>
}
