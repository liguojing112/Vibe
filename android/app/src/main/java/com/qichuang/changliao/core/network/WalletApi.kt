package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.CreateRechargeOrderBody
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/**
 * 金币钱包（docs/05 第 4.7 节，P13/P22/P24）。**只给男用户**，全部要登录。
 *
 * 充值支持两条路：客服人工充值（后台），以及客户 2026-10-26 新增的码支付在线充值。
 * 在线支付只传档位下标，金额由服务端定。
 */
interface WalletApi {

    @GET("api/v1/wallet/coin")
    suspend fun coin(): ApiResponse<JsonElement?>

    @GET("api/v1/wallet/coin/logs")
    suspend fun coinLogs(
        @Query("type") type: String,
        @Query("page") page: Int,
    ): ApiResponse<JsonElement?>

    @GET("api/v1/wallet/recharge-info")
    suspend fun rechargeInfo(): ApiResponse<JsonElement?>

    @POST("api/v1/wallet/recharge/order")
    suspend fun createRechargeOrder(
        @Body body: CreateRechargeOrderBody,
    ): ApiResponse<JsonElement?>

    @GET("api/v1/wallet/recharge/order/{orderNo}")
    suspend fun rechargeOrderStatus(
        @Path("orderNo") orderNo: String,
    ): ApiResponse<JsonElement?>
}
