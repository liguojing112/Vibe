package com.qichuang.changliao.core.data.repository

import com.qichuang.changliao.core.data.model.Agreement
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.AppApi
import com.qichuang.changliao.core.network.AuthApi
import com.qichuang.changliao.core.network.FakeTokenStore
import com.qichuang.changliao.core.network.buildTestRetrofit
import com.qichuang.changliao.core.network.successTokenBody
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
 * 认证仓库（docs/05 第 4.1 节）：**登录 / 注册成功要把 token 存下来**，
 * 业务错误一个字节都不能存（否则页面会以为已登录）。
 */
class AuthRepositoryTest {

    private lateinit var server: MockWebServer
    private lateinit var tokenStore: FakeTokenStore
    private lateinit var repository: AuthRepository

    @Before
    fun setUp() {
        server = MockWebServer()
        server.start()
        tokenStore = FakeTokenStore()
        val retrofit = buildTestRetrofit(server.url("/").toString(), tokenStore)
        repository = AuthRepository(
            authApi = retrofit.create(AuthApi::class.java),
            appApi = retrofit.create(AppApi::class.java),
            tokenStore = tokenStore,
        )
    }

    @After
    fun tearDown() {
        runCatching { server.shutdown() }
    }

    private fun ok(body: String) = MockResponse().setResponseCode(200).setBody(body)

    private fun bizError(code: Int, message: String) =
        MockResponse().setResponseCode(200).setBody("""{"code":$code,"message":"$message","data":null}""")

    @Test
    fun `登录成功保存 token`() = runTest {
        server.enqueue(ok(successTokenBody(access = "a1", refresh = "r1")))

        val result = repository.login("13800138000", "abc123")

        assertTrue(result is ApiResult.Success)
        assertEquals("a1", tokenStore.accessToken())
        assertEquals("r1", tokenStore.refreshToken())

        val recorded = server.takeRequest()
        assertEquals("/api/v1/auth/login", recorded.path)
        val body = recorded.body.readUtf8()
        assertTrue(body.contains("\"account\":\"13800138000\""))
        assertTrue(body.contains("\"password\":\"abc123\""))
    }

    @Test
    fun `登录业务错误不保存 token`() = runTest {
        server.enqueue(bizError(10001, "账号或密码错误"))

        val result = repository.login("13800138000", "wrong")

        val error = result as ApiResult.BizError
        assertEquals(10001, error.code)
        assertEquals("账号或密码错误", error.message)
        assertNull(tokenStore.accessToken())
        assertNull(tokenStore.refreshToken())
    }

    @Test
    fun `登录网络错误不保存 token`() = runTest {
        server.enqueue(MockResponse().setResponseCode(500))

        val result = repository.login("13800138000", "abc123")

        assertTrue(result is ApiResult.NetworkError)
        assertNull(tokenStore.accessToken())
    }

    @Test
    fun `注册成功保存 token 且请求体字段齐全`() = runTest {
        server.enqueue(ok(successTokenBody(access = "a2", refresh = "r2")))

        val result = repository.register(
            phone = "13800138000",
            password = "abc123",
            confirmPassword = "abc123",
            gender = 2,
            nickname = "小满",
        )

        assertTrue(result is ApiResult.Success)
        assertEquals("a2", tokenStore.accessToken())

        val body = server.takeRequest().body.readUtf8()
        assertTrue(body.contains("\"phone\":\"13800138000\""))
        assertTrue(body.contains("\"confirm_password\":\"abc123\""))
        assertTrue(body.contains("\"gender\":2"))
        assertTrue(body.contains("\"nickname\":\"小满\""))
    }

    @Test
    fun `协议接口能解析出内容`() = runTest {
        server.enqueue(ok("""{"code":200,"message":"成功","data":{"type":"user","content":"付费聊天声明"}}"""))

        val result = repository.agreement(Agreement.TYPE_USER)

        val success = result as ApiResult.Success
        assertEquals("user", success.data.type)
        assertEquals("付费聊天声明", success.data.content)
        assertEquals("/api/v1/app/agreement/user", server.takeRequest().path)
    }

    @Test
    fun `退出登录即使接口失败也要清掉本地 token`() = runTest {
        tokenStore.saveTokens("a1", "r1")
        server.enqueue(MockResponse().setResponseCode(500))

        repository.logout()

        assertNull(tokenStore.accessToken())
        assertTrue(tokenStore.cleared)
    }
}
