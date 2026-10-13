package com.qichuang.changliao.core.network

import kotlinx.serialization.json.JsonElement
import retrofit2.http.GET
import retrofit2.http.Query

/**
 * 金币钱包（docs/05 第 4.7 节，P13/P22/P24）。**只给男用户**，全部要登录。
 *
 * 没有「充值」接口：充值由后台客服人工完成（docs/06 第 8 节），
 * App 里点充值只是把客服联系方式给用户。
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
}
