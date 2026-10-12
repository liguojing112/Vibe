package com.qichuang.changliao.feature.auth

import com.qichuang.changliao.core.common.Validators
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.repository.AuthRepository
import com.qichuang.changliao.core.network.ApiResult
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
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
 * 注册页（docs/01 P00）：手机号 11 位、密码 6~20 位含字母数字、确认密码一致、性别必选、昵称 2~12 字。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class RegisterViewModelTest {

    private val repository = mockk<AuthRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = RegisterViewModel(repository)

    private fun RegisterViewModel.fillIn() {
        onPhoneChange("13800138000")
        onPasswordChange("abc123")
        onConfirmPasswordChange("abc123")
        onGenderChange(Gender.FEMALE)
        onNicknameChange("小满")
    }

    @Test
    fun `手机号不合法提示且不请求`() {
        val viewModel = viewModel()
        viewModel.fillIn()
        viewModel.onPhoneChange("1380013800")

        viewModel.submit()

        assertEquals(Validators.PHONE_INVALID, viewModel.uiState.value.phoneError)
        coVerify(exactly = 0) { repository.register(any(), any(), any(), any(), any()) }
    }

    @Test
    fun `密码不含字母数字组合时提示`() {
        val viewModel = viewModel()
        viewModel.fillIn()
        viewModel.onPasswordChange("abcdefgh")

        viewModel.submit()

        assertEquals(Validators.PASSWORD_INVALID, viewModel.uiState.value.passwordError)
        coVerify(exactly = 0) { repository.register(any(), any(), any(), any(), any()) }
    }

    @Test
    fun `两次密码不一致时提示`() {
        val viewModel = viewModel()
        viewModel.fillIn()
        viewModel.onConfirmPasswordChange("abc124")

        viewModel.submit()

        assertEquals("两次输入的密码不一致", viewModel.uiState.value.confirmPasswordError)
        coVerify(exactly = 0) { repository.register(any(), any(), any(), any(), any()) }
    }

    @Test
    fun `确认密码没填时提示`() {
        val viewModel = viewModel()
        viewModel.fillIn()
        viewModel.onConfirmPasswordChange("")

        viewModel.submit()

        assertEquals("请再输入一次密码", viewModel.uiState.value.confirmPasswordError)
    }

    @Test
    fun `没选性别时提示`() {
        val viewModel = viewModel()
        viewModel.fillIn()
        viewModel.onGenderChange(Gender.UNKNOWN)

        viewModel.submit()

        assertEquals("请选择性别", viewModel.uiState.value.genderError)
        coVerify(exactly = 0) { repository.register(any(), any(), any(), any(), any()) }
    }

    @Test
    fun `昵称太短时提示`() {
        val viewModel = viewModel()
        viewModel.fillIn()
        viewModel.onNicknameChange("我")

        viewModel.submit()

        assertEquals(Validators.NICKNAME_INVALID, viewModel.uiState.value.nicknameError)
        coVerify(exactly = 0) { repository.register(any(), any(), any(), any(), any()) }
    }

    @Test
    fun `全部合法才请求且参数正确`() {
        val viewModel = viewModel()
        coEvery { repository.register(any(), any(), any(), any(), any()) } returns ApiResult.Success(Unit)
        viewModel.fillIn()
        viewModel.onNicknameChange("  小满  ")

        viewModel.submit()

        coVerify(exactly = 1) {
            repository.register(
                phone = "13800138000",
                password = "abc123",
                confirmPassword = "abc123",
                gender = Gender.FEMALE,
                nickname = "小满",
            )
        }
    }

    @Test
    fun `注册成功进入主界面`() = runTest {
        val viewModel = viewModel()
        coEvery { repository.register(any(), any(), any(), any(), any()) } returns ApiResult.Success(Unit)
        viewModel.fillIn()

        viewModel.submit()

        assertEquals(Unit, viewModel.goMain.first())
    }

    @Test
    fun `10002 账号已存在用服务端文案`() {
        val viewModel = viewModel()
        coEvery { repository.register(any(), any(), any(), any(), any()) } returns
            ApiResult.BizError(10002, "账号已存在")
        viewModel.fillIn()

        viewModel.submit()

        assertEquals("账号已存在", viewModel.uiState.value.toast)
        assertNull(viewModel.uiState.value.phoneError)
    }
}
