package com.qichuang.changliao.core.network

import com.qichuang.changliao.core.network.dto.LoginRequestDto
import com.qichuang.changliao.core.network.dto.TokenDto
import kotlinx.coroutines.test.runTest
import okhttp3.mockwebserver.MockResponse
import okhttp3.mockwebserver.MockWebServer
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * [safeApiCall] 把三种情况映射对：成功 / 业务错误 / 网络错误（docs/02 第 4.3 节）。
 */
class ApiResultTest {

    private lateinit var server: MockWebServer
    private lateinit var api: AuthApi

    private fun loginBody() = LoginRequestDto(account = "13800138000", password = "abc12345")

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
        api = buildTestAuthApi(server.url("/").toString(), FakeTokenStore())
    }

    @After
    fun tearDown() {
        runCatching { server.shutdown() }
    }

    @Test
    fun `code 200 返回 Success 并解析出 data`() = runTest {
        server.enqueue(
            MockResponse().setResponseCode(200).setBody(successTokenBody("a1", "r1"))
        )

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertTrue(result is ApiResult.Success)
        assertEquals("a1", (result as ApiResult.Success).data.accessToken)
        assertEquals("r1", result.data.refreshToken)
    }

    @Test
    fun `业务码非 200 返回 BizError 且带回 message`() = runTest {
        server.enqueue(
            MockResponse().setResponseCode(200)
                .setBody("""{"code":10001,"message":"账号或密码错误","data":null}""")
        )

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertTrue(result is ApiResult.BizError)
        assertEquals(10001, (result as ApiResult.BizError).code)
        assertEquals("账号或密码错误", result.message)
    }

    @Test
    fun `兼容 20001 余额不足这类业务码`() = runTest {
        server.enqueue(
            MockResponse().setResponseCode(200)
                .setBody("""{"code":20001,"message":"金币余额不足","data":{"balance":4,"needed":5}}""")
        )

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertEquals(20001, (result as ApiResult.BizError).code)
    }

    @Test
    fun `HTTP 401 且没有 refresh token 时返回未登录业务错误`() = runTest {
        server.enqueue(
            MockResponse().setResponseCode(401)
                .setBody("""{"code":401,"message":"请先登录","data":null}""")
        )

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertTrue(result is ApiResult.BizError)
        assertEquals(API_CODE_UNAUTHORIZED, (result as ApiResult.BizError).code)
    }

    @Test
    fun `连不上服务时返回 NetworkError`() = runTest {
        server.shutdown()

        val result = safeApiCall<TokenDto> { api.login(loginBody()) }

        assertTrue(result is ApiResult.NetworkError)
        assertEquals(NETWORK_ERROR_MESSAGE, (result as ApiResult.NetworkError).message)
    }
}
