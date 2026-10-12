package com.qichuang.changliao.core.data.repository

import android.content.Context
import android.net.Uri
import com.qichuang.changliao.core.common.ImageCompressor
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import com.qichuang.changliao.core.network.UploadApi
import com.qichuang.changliao.core.network.dto.UploadResultDto
import com.qichuang.changliao.core.network.safeApiCall
import dagger.hilt.android.qualifiers.ApplicationContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import javax.inject.Inject
import javax.inject.Singleton

/** 上传场景（docs/05 第 4.2 节）。 */
object UploadScene {
    const val AVATAR = "avatar"
    const val PHOTO = "photo"
    const val CHAT = "chat"
    const val REPORT = "report"
    const val REAL_NAME = "real_name"
}

/**
 * 选图 → 压缩 → 上传，返回可用的图片地址（docs/02 第 4.4 节：长边 1080、质量 80）。
 *
 * 把 `Context` 和 `Uri` 关在这一层里，ViewModel 只拿到 `ByteArray`/`Uri` 之外的结果，
 * 不用碰 Android API。
 */
@Singleton
class ImageUploader @Inject constructor(
    @ApplicationContext private val context: Context,
    private val uploadApi: UploadApi,
) {

    /** 压缩失败 / 读不出图时给用户看的文案（不是网络问题，别用统一那句）。 */
    private val readFailedMessage = "图片读不出来，换一张试试"

    suspend fun upload(uri: Uri, scene: String): ApiResult<String> {
        val bytes = runCatching { ImageCompressor.compress(context, uri) }
            .getOrElse { return ApiResult.NetworkError(readFailedMessage) }

        val file = MultipartBody.Part.createFormData(
            name = "file",
            filename = "image.jpg",
            body = bytes.toRequestBody(JPEG_MEDIA_TYPE),
        )

        return when (
            val result = safeApiCall<UploadResultDto> {
                uploadApi.uploadImage(file, scene.toRequestBody(TEXT_MEDIA_TYPE))
            }
        ) {
            is ApiResult.Success -> if (result.data.url.isBlank()) {
                ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
            } else {
                ApiResult.Success(result.data.url)
            }

            is ApiResult.BizError -> result
            is ApiResult.NetworkError -> result
        }
    }

    private companion object {
        val JPEG_MEDIA_TYPE = "image/jpeg".toMediaType()

        val TEXT_MEDIA_TYPE = "text/plain".toMediaType()
    }
}
