package com.qichuang.changliao.core.network

import kotlinx.serialization.json.JsonElement
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part

/**
 * 图片上传（docs/05 第 4.2 节）：multipart `file` + `scene`，返回 `{url}`。要登录。
 */
interface UploadApi {

    @Multipart
    @POST("api/v1/upload/image")
    suspend fun uploadImage(
        @Part file: MultipartBody.Part,
        @Part("scene") scene: RequestBody,
    ): ApiResponse<JsonElement?>
}
