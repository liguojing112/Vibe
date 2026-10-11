package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.LoginRequestDto
import com.qichuang.changliao.core.network.dto.TokenDto
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * AuthInterceptor（docs/02 第 4.3 节）：加 token、401 刷新一次、刷新失败清登录态、跨主机不带 token。
 */
class AuthInterceptorTest {

    private lateinit var server: MockWebServer
    private lateinit var tokenStore: FakeTokenStore
    private lateinit var api: AuthApi

    private fun loginBody() = LoginRequestDto(account = "13800138000", password = "abc12345")

    private fun unauthorized() = MockResponse().setResponseCode(401)
        .setBody("""{"code":401,"message":"请先登录","data":null}""")

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
    }

    @After
    fun tearDown() {
        runCatching { server.shutdown() }
    }

    private fun buildApi(store: FakeTokenStore) {
        tokenStore = store
        api = buildTestAuthApi(server.url("/").toString(), store)
    }

    @Test
    fun `有 token 时自动带上 Authorization 头`() = runTest {
        buildApi(FakeTokenStore(access = "access-1", refresh = "refresh-1"))
        server.enqueue(MockResponse().setResponseCode(200).setBody(successTokenBody()))

        safeApiCall<TokenDto> { api.login(loginBody()) }

        assertEquals("Bearer access-1", server.takeRequest().getHeader("Authorization"))
    }

    @Test
    fun `没有 token 时不带 Authorization 头`() = runTest {
        buildApi(FakeTokenStore())
        server.enqueue(MockResponse().setResponseCode(200).setBody(successTokenBody()))

        safeApiCall<TokenDto> { api.login(loginBody()) }

        assertNull(server.takeRequest().getHeader("Authorization"))
    }

    @Test
    fun `收到 401 时刷新一次并用新 token 重放`() = runTest {
        val store = FakeTokenStore(access = "old-access", refresh = "old-refresh")
        buildApi(store)

        server.enqueue(unauthorized())
        server.enqueue(MockResponse().setResponseCode(200).setBody(successTokenBody("new-access", "new-refresh")))
        server.enqueue(MockResponse().setResponseCode(200).setBody(successTokenBody("new-access", "new-refresh")))

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertTrue(result is ApiResult.Success)
        assertEquals("new-access", store.accessToken())
        assertEquals("new-refresh", store.refreshToken())
        assertEquals(3, server.requestCount)

        server.takeRequest() // 第一次业务请求
        val refreshRequest = server.takeRequest()
        assertTrue(refreshRequest.path.orEmpty().contains("/api/v1/auth/refresh"))
        assertEquals("Bearer new-access", server.takeRequest().getHeader("Authorization"))
    }

    @Test
    fun `刷新也失败时清登录态并返回未登录`() = runTest {
        val store = FakeTokenStore(access = "old-access", refresh = "old-refresh")
        buildApi(store)

        server.enqueue(unauthorized())
        // 刷新接口返回业务码 401（refresh token 也过期了）
        server.enqueue(
            MockResponse().setResponseCode(200)
                .setBody("""{"code":401,"message":"登录已失效，请重新登录","data":null}""")
        )

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertTrue(result is ApiResult.BizError)
        assertEquals(API_CODE_UNAUTHORIZED, (result as ApiResult.BizError).code)
        assertTrue(store.cleared)
        assertNull(store.accessToken())
        assertNull(store.refreshToken())
    }

    @Test
    fun `刷新请求本身不会再触发刷新`() = runTest {
        val store = FakeTokenStore(access = "old-access", refresh = "old-refresh")
        buildApi(store)

        server.enqueue(unauthorized())

        val result = safeApiCall<TokenDto> {
            api.refresh(com.qichuang.changliao.core.network.dto.RefreshRequestDto("old-refresh"))
        }

        assertTrue(result is ApiResult.BizError)
        // 只发了一次请求，没有递归刷新
        assertEquals(1, server.requestCount)
        assertTrue(!store.cleared)
    }
}
