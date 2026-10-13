package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.RealNameSubmitRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/**
 * 实名认证（docs/05 第 4.8 节，P29）。App 只能提交和看状态，审核在后台。
 */
interface RealNameApi {

    @GET("api/v1/realname")
    suspend fun show(): ApiResponse<JsonElement?>

    @POST("api/v1/realname/submit")
    suspend fun submit(@Body body: RealNameSubmitRequestDto): ApiResponse<JsonElement?>
}
