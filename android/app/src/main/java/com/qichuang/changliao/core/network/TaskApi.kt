package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.TaskClaimRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/**
 * 每日任务（docs/05 第 4.9 节，P11）。要登录。
 *
 * 完成条件全部在服务端，App 只负责点按钮。
 */
interface TaskApi {

    @GET("api/v1/task/list")
    suspend fun list(): ApiResponse<JsonElement?>

    @POST("api/v1/task/sign-in")
    suspend fun signIn(): ApiResponse<JsonElement?>

    @POST("api/v1/task/claim")
    suspend fun claim(@Body body: TaskClaimRequestDto): ApiResponse<JsonElement?>
}
