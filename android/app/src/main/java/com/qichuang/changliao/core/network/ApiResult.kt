package com.qichuang.changliao.core.network

import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.decodeFromJsonElement
import retrofit2.HttpException
import java.io.IOException

/**
 * 统一结果封装（docs/02 第 4.3 节）：页面只认这三种结果，不用管 Retrofit / 异常。
 */
sealed interface ApiResult<out T> {
    /** 成功，[data] 是业务数据 */
    data class Success<T>(val data: T) : ApiResult<T>

    /**
     * 业务错误：HTTP 200 但 code 不是 200（例如 20001 金币不足）。
     * [data] 保留原始 JSON——业务错误的 data 结构和成功时不一样（20001 会带 balance/needed），
     * 需要时自己从里面取。
     */
    data class BizError(
        val code: Int,
        val message: String,
        val data: JsonElement? = null,
    ) : ApiResult<Nothing>

    /** 网络问题（超时、断网、解析失败等） */
    data class NetworkError(val message: String) : ApiResult<Nothing>
}

/** 网络错误统一文案（docs/03 第 6 节第 5 条）。 */
const val NETWORK_ERROR_MESSAGE = "网络不太好，请稍后重试"

/**
 * 把一次接口调用包成 [ApiResult]：
 *
 * - code == 200 → 按 [T] 解析 `data` → [ApiResult.Success]
 * - code != 200 → [ApiResult.BizError]（保留原始 data）
 * - 网络 / 解析异常 → [ApiResult.NetworkError]
 *
 * 401 由 [AuthInterceptor] 处理过；走到这里说明刷新也失败，同样按未登录给上层。
 */
suspend inline fun <reified T : Any> safeApiCall(
    crossinline block: suspend () -> ApiResponse<JsonElement?>,
): ApiResult<T> = try {
    val response = block()
    val data = response.data
    when {
        response.code == API_CODE_SUCCESS && data != null && data !is JsonNull ->
            ApiResult.Success(clJson.decodeFromJsonElement<T>(data))

        response.code == API_CODE_SUCCESS ->
            ApiResult.BizError(response.code, response.message ?: "数据为空")

        else -> ApiResult.BizError(response.code, response.message.orEmpty(), data)
    }
} catch (e: HttpException) {
    if (e.code() == API_CODE_UNAUTHORIZED) {
        ApiResult.BizError(API_CODE_UNAUTHORIZED, "登录已失效，请重新登录")
    } else {
        ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
    }
} catch (e: IOException) {
    ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
} catch (e: Exception) {
    ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
}
