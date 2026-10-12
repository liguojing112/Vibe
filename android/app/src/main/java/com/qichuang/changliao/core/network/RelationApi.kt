package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.RelationTargetDto
import kotlinx.serialization.json.JsonElement
import retrofit2.http.Body
import retrofit2.http.POST

/**
 * 关系接口（docs/05 第 4.4 节）：关注、取关、拉黑、解除拉黑。
 *
 * 四个接口成功时 `data` 都是 null，所以配 `safeApiCallUnit`。
 */
interface RelationApi {

    @POST("api/v1/relation/follow")
    suspend fun follow(@Body body: RelationTargetDto): ApiResponse<JsonElement?>

    @POST("api/v1/relation/unfollow")
    suspend fun unfollow(@Body body: RelationTargetDto): ApiResponse<JsonElement?>

    @POST("api/v1/relation/block")
    suspend fun block(@Body body: RelationTargetDto): ApiResponse<JsonElement?>

    @POST("api/v1/relation/unblock")
    suspend fun unblock(@Body body: RelationTargetDto): ApiResponse<JsonElement?>
}

