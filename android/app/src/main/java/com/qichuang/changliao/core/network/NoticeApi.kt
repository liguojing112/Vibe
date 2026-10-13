package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.ReportRequestDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

/**
 * 系统消息与举报（docs/05 第 4.9 节，P10 / P05 的举报入口）。
 */
interface NoticeApi {

    /** 通知列表。**调用后该分类标为已读**（docs/05 原文）。 */
    @GET("api/v1/notice/list")
    suspend fun list(
        @Query("category") category: Int,
        @Query("page") page: Int,
    ): ApiResponse<JsonElement?>

    @POST("api/v1/report")
    suspend fun report(@Body body: ReportRequestDto): ApiResponse<JsonElement?>
}
