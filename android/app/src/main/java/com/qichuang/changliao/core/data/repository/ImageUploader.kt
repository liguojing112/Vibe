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
import java.io.File
import java.util.UUID
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
    const val MOMENT = "moment"
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

    suspend fun upload(uri: Uri, scene: String): ApiResult<String> =
        when (val bytes = compress(uri)) {
            is ApiResult.Success -> put(bytes.data, scene)
            is ApiResult.BizError -> bytes
            is ApiResult.NetworkError -> bytes
        }

    /**
     * 聊天图片第一步：**先压缩并存一份到本地**（docs/10 第 2.5 节）。
     *
     * 顺序很重要：本地先有一份，气泡才能立刻显示出来（不用等上传），
     * 而且**上传失败/发送失败重发时直接复用这份文件**，不用让用户重新选图。
     *
     * @return 本地文件路径
     */
    suspend fun prepareChatImage(uri: Uri): ApiResult<String> =
        when (val bytes = compress(uri)) {
            is ApiResult.Success -> {
                val path = runCatching {
                    val dir = File(context.filesDir, CHAT_IMAGE_DIR).apply { mkdirs() }
                    val file = File(dir, UUID.randomUUID().toString().replace("-", "") + ".jpg")
                    file.writeBytes(bytes.data)
                    file.absolutePath
                }.getOrNull()

                if (path == null) ApiResult.NetworkError(readFailedMessage) else ApiResult.Success(path)
            }

            is ApiResult.BizError -> bytes
            is ApiResult.NetworkError -> bytes
        }

    /** 聊天图片第二步：把本地那份传上去，拿到可用的图片地址。 */
    suspend fun uploadChatImage(localPath: String, scene: String = UploadScene.CHAT): ApiResult<String> {
        val bytes = runCatching { File(localPath).readBytes() }.getOrNull()
            ?: return ApiResult.NetworkError(readFailedMessage)

        return put(bytes, scene)
    }

    private suspend fun compress(uri: Uri): ApiResult<ByteArray> {
        val bytes = runCatching { ImageCompressor.compress(context, uri) }.getOrNull()

        return if (bytes == null) ApiResult.NetworkError(readFailedMessage) else ApiResult.Success(bytes)
    }

    private suspend fun put(bytes: ByteArray, scene: String): ApiResult<String> {
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
        /** 聊天图片的本地目录：生命周期跟着聊天记录走（docs/10 第 4 节）。 */
        const val CHAT_IMAGE_DIR = "chat_images"

        val JPEG_MEDIA_TYPE = "image/jpeg".toMediaType()

        val TEXT_MEDIA_TYPE = "text/plain".toMediaType()
    }
}
