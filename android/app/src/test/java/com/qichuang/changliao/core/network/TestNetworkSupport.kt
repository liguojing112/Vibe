package com.qichuang.changliao.core.network

import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flowOf
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import javax.inject.Provider

/** 内存版登录态，测试用。 */
class FakeTokenStore(
    var access: String? = null,
    var refresh: String? = null,
) : TokenStore {

    var cleared: Boolean = false
        private set

    override fun accessToken(): String? = access

    override fun refreshToken(): String? = refresh

    override fun accessTokenFlow(): Flow<String?> = flowOf(access)

    override fun saveTokens(accessToken: String, refreshToken: String) {
        access = accessToken
        refresh = refreshToken
    }

    override fun clear() {
        cleared = true
        access = null
        refresh = null
    }
}

/** 测试里的成功响应体。 */
fun successTokenBody(access: String = "access-token", refresh: String = "refresh-token"): String =
    """{"code":200,"message":"成功","data":{"access_token":"$access","refresh_token":"$refresh","expire_at":604800}}"""

/** 用 MockWebServer 的地址装一套 Retrofit（带 AuthInterceptor）。 */
fun buildTestAuthApi(serverUrl: String, tokenStore: TokenStore): AuthApi {
    lateinit var holder: AuthApi
    val interceptor = AuthInterceptor(
        tokenStore = tokenStore,
        refreshApi = Provider { holder },
        baseUrl = serverUrl,
    )
    val client = OkHttpClient.Builder()
        .addInterceptor(interceptor)
        .build()

    holder = Retrofit.Builder()
        .baseUrl(serverUrl)
        .client(client)
        .addConverterFactory(
            Json { ignoreUnknownKeys = true }.asConverterFactory("application/json".toMediaType())
        )
        .build()
        .create(AuthApi::class.java)

    return holder
}
