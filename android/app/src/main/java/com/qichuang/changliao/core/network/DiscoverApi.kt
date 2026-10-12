package com.qichuang.changliao.core.network

import kotlinx.serialization.json.JsonElement
import retrofit2.http.GET
import retrofit2.http.Query

/**
 * 发现页接口（docs/05 第 4.3 节）。要登录（`AuthInterceptor` 带 token）。
 *
 * 「广场」就是发现页的数据，不另开接口（客户 2026-10-11 确认）。
 */
interface DiscoverApi {

    @GET("api/v1/discover")
    suspend fun discover(
        @Query("tab") tab: String,
        @Query("page") page: Int,
        @Query("city") city: String? = null,
        @Query("age_min") ageMin: Int? = null,
        @Query("age_max") ageMax: Int? = null,
        @Query("purpose") purposes: List<String>? = null,
        @Query("verified") verified: Int? = null,
        @Query("complete") complete: Int? = null,
        @Query("online_only") onlineOnly: Int? = null,
    ): ApiResponse<JsonElement?>

    @GET("api/v1/discover/search")
    suspend fun search(
        @Query("keyword") keyword: String,
        @Query("page") page: Int,
    ): ApiResponse<JsonElement?>
}
