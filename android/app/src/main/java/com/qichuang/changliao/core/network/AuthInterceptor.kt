package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.RefreshRequestDto
import com.qichuang.changliao.core.network.dto.TokenDto
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.decodeFromJsonElement
import okhttp3.Interceptor
import okhttp3.Response
import java.util.concurrent.TimeUnit
import kotlinx.coroutines.runBlocking
import javax.inject.Inject
import javax.inject.Provider
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull

/** 标了这个头的请求不做「401 自动刷新」（登录、注册、刷新本身）。 */
const val HEADER_NO_AUTH = "X-No-Auth"

private const val HEADER_NO_AUTH_VALUE = "true"

/**
 * 鉴权拦截器（docs/02 第 4.3、4.4 节）：
 *
 * 1. 给发往**本服务**的请求加 `Authorization: Bearer <access_token>`
 *    —— 跨主机（例如以后图片走 CDN）**不带** token（docs/10 第 4 节）；
 * 2. 收到 401 时用 refresh_token **自动刷新一次**并重放请求；
 * 3. 刷新失败 → 清登录态（页面看到没有 token 会回登录页）。
 */
class AuthInterceptor @Inject constructor(
    private val tokenStore: TokenStore,
    private val refreshApi: Provider<AuthApi>,
    @BaseUrl private val baseUrl: String,
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val original = chain.request()
        val accessToken = tokenStore.accessToken()
        val sameHost = isSameHost(original.url.host)

        val request = if (accessToken != null && sameHost) {
            original.newBuilder().header("Authorization", "Bearer $accessToken").build()
        } else {
            original
        }

        val response = chain.proceed(request)

        val skipRefresh = original.header(HEADER_NO_AUTH) == HEADER_NO_AUTH_VALUE
        if (response.code != API_CODE_UNAUTHORIZED || skipRefresh) {
            return response
        }

        val refreshToken = tokenStore.refreshToken()
        val tokens = if (refreshToken == null) {
            null
        } else {
            runBlocking {
                runCatching {
                    val body = refreshApi.get().refresh(RefreshRequestDto(refreshToken))
                    val data = body.data
                    if (body.code == API_CODE_SUCCESS && data != null && data !is JsonNull) {
                        clJson.decodeFromJsonElement<TokenDto>(data)
                    } else {
                        null
                    }
                }.getOrNull()
            }
        }

        if (tokens == null) {
            tokenStore.clear()
            return response
        }

        tokenStore.saveTokens(tokens.accessToken, tokens.refreshToken)
        response.close()

        return chain.proceed(
            request.newBuilder()
                .header("Authorization", "Bearer ${tokens.accessToken}")
                .build()
        )
    }

    private fun isSameHost(host: String): Boolean =
        baseUrl.toHttpUrlOrNull()?.host?.equals(host, ignoreCase = true) ?: false

    companion object {
        /** 依赖方用这个构造 OkHttpClient（超时 30 秒，docs/10 第 6 节）。 */
        const val TIMEOUT_SECONDS = 30L

        fun timeouts(builder: okhttp3.OkHttpClient.Builder): okhttp3.OkHttpClient.Builder =
            builder
                .connectTimeout(TIMEOUT_SECONDS, TimeUnit.SECONDS)
                .readTimeout(TIMEOUT_SECONDS, TimeUnit.SECONDS)
                .writeTimeout(TIMEOUT_SECONDS, TimeUnit.SECONDS)
    }
}
