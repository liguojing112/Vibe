package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.CreateMomentCommentRequestDto
import com.qichuang.changliao.core.network.dto.PublishMomentRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

/** 朋友圈（docs/01 P05 之后的新功能）。全部要登录。 */
interface MomentApi {

    /** 某个用户的动态。 */
    @GET("api/v1/moment/user/{id}")
    suspend fun userMoments(
        @Path("id") userId: Long,
        @Query("page") page: Int,
    ): ApiResponse<JsonElement?>

    @GET("api/v1/moment/mine")
    suspend fun mine(@Query("page") page: Int): ApiResponse<JsonElement?>

    @POST("api/v1/moment")
    suspend fun publish(@Body body: PublishMomentRequestDto): ApiResponse<JsonElement?>

    @DELETE("api/v1/moment/{id}")
    suspend fun remove(@Path("id") id: Long): ApiResponse<JsonElement?>

    @POST("api/v1/moment/{id}/like")
    suspend fun like(@Path("id") id: Long): ApiResponse<JsonElement?>

    @DELETE("api/v1/moment/{id}/like")
    suspend fun unlike(@Path("id") id: Long): ApiResponse<JsonElement?>

    @GET("api/v1/moment/{id}/comment")
    suspend fun comments(
        @Path("id") id: Long,
        @Query("page") page: Int,
    ): ApiResponse<JsonElement?>

    @POST("api/v1/moment/{id}/comment")
    suspend fun comment(
        @Path("id") id: Long,
        @Body body: CreateMomentCommentRequestDto,
    ): ApiResponse<JsonElement?>
}
