package com.qichuang.changliao.feature.auth

import com.qichuang.changliao.core.data.repository.AuthRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.runTest
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

/**
 * 登录页（docs/01 P00）验收：校验错误提示、登录成功保存 token（在 [com.qichuang.changliao.core.data.repository.AuthRepositoryTest] 里验）、
 * 10003/10004 提示。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class LoginViewModelTest {

    private val repository = mockk<AuthRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = LoginViewModel(repository)

    private fun LoginViewModel.fillIn(account: String = "13800138000", password: String = "abc123") {
        onAccountChange(account)
        onPasswordChange(password)
        onAgreedChange(true)
    }

    @Test
    fun `账号为空时不发请求并提示`() {
        val viewModel = viewModel()
        viewModel.fillIn(account = "")

        viewModel.submit()

        assertEquals("请输入手机号或账号", viewModel.uiState.value.accountError)
        coVerify(exactly = 0) { repository.login(any(), any(), any()) }
    }

    @Test
    fun `密码为空时不发请求并提示`() {
        val viewModel = viewModel()
        viewModel.fillIn(password = "")

        viewModel.submit()

        assertEquals("请输入密码", viewModel.uiState.value.passwordError)
        coVerify(exactly = 0) { repository.login(any(), any(), any()) }
    }

    @Test
    fun `没勾协议时不发请求并提示`() {
        val viewModel = viewModel()
        viewModel.onAccountChange("13800138000")
        viewModel.onPasswordChange("abc123")

        viewModel.submit()

        assertEquals("请先阅读并同意《用户协议》《隐私政策》", viewModel.uiState.value.agreementError)
        coVerify(exactly = 0) { repository.login(any(), any(), any()) }
    }

    @Test
    fun `改字段会把该字段的错误清掉`() {
        val viewModel = viewModel()
        viewModel.submit()
        assertEquals("请输入手机号或账号", viewModel.uiState.value.accountError)

        viewModel.onAccountChange("13800138000")

        assertNull(viewModel.uiState.value.accountError)
    }

    @Test
    fun `校验通过才发请求且账号去空格`() {
        val viewModel = viewModel()
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.Success(Unit)
        viewModel.fillIn(account = "  13800138000  ")

        viewModel.submit()

        coVerify(exactly = 1) { repository.login("13800138000", "abc123", any()) }
    }

    @Test
    fun `登录成功进入主界面`() = runTest {
        val viewModel = viewModel()
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.Success(Unit)
        viewModel.fillIn()

        viewModel.submit()

        assertEquals(Unit, viewModel.goMain.first())
        assertNull(viewModel.uiState.value.toast)
    }

    @Test
    fun `10003 账号锁定用服务端文案`() {
        val viewModel = viewModel()
        val message = "密码错误次数过多，账号已锁定，请 15 分钟后再试"
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.BizError(10003, message)
        viewModel.fillIn()

        viewModel.submit()

        assertEquals(message, viewModel.uiState.value.toast)
    }

    @Test
    fun `10004 封禁用服务端文案`() {
        val viewModel = viewModel()
        val message = "账号已被封禁：发布虚假资料（解封时间 2026-11-01 00:00:00）"
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.BizError(10004, message)
        viewModel.fillIn()

        viewModel.submit()

        assertEquals(message, viewModel.uiState.value.toast)
    }

    @Test
    fun `10001 账号或密码错误用服务端文案`() {
        val viewModel = viewModel()
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.BizError(10001, "账号或密码错误")
        viewModel.fillIn()

        viewModel.submit()

        assertEquals("账号或密码错误", viewModel.uiState.value.toast)
    }

    @Test
    fun `网络错误用统一文案`() {
        val viewModel = viewModel()
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        viewModel.fillIn()

        viewModel.submit()

        assertEquals(NETWORK_ERROR_MESSAGE, viewModel.uiState.value.toast)
    }

    @Test
    fun `提示显示过以后可以被清掉`() {
        val viewModel = viewModel()
        coEvery { repository.login(any(), any(), any()) } returns ApiResult.BizError(10001, "账号或密码错误")
        viewModel.fillIn()
        viewModel.submit()

        viewModel.onToastShown()

        assertNull(viewModel.uiState.value.toast)
    }

    @Test
    fun `提交中重复点击不会发第二次请求`() {
        val viewModel = viewModel()
        val blocker = CompletableDeferred<Unit>()
        coEvery { repository.login(any(), any(), any()) } coAnswers {
            blocker.await()
            ApiResult.Success(Unit)
        }
        viewModel.fillIn()

        viewModel.submit()
        viewModel.submit()

        coVerify(exactly = 1) { repository.login(any(), any(), any()) }
    }

    @Test
    fun `提交中按钮是 loading 状态`() {
        val viewModel = viewModel()
        val blocker = CompletableDeferred<Unit>()
        coEvery { repository.login(any(), any(), any()) } coAnswers {
            blocker.await()
            ApiResult.Success(Unit)
        }
        viewModel.fillIn()

        viewModel.submit()

        assertEquals(true, viewModel.uiState.value.submitting)
    }
}
