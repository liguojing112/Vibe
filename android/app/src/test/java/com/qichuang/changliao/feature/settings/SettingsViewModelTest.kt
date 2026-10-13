package com.qichuang.changliao.feature.settings

import com.qichuang.changliao.core.data.local.ClDataStore
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AuthRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.every
import io.mockk.just
import io.mockk.mockk
import io.mockk.runs
import io.mockk.verify
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 设置（P15）：本地开关、服务端开关失败要切回来、改密码校验、注销。
 *
 * 「开关失败不能骗用户」是这里最要紧的一条 —— 乐观切换好看，但失败了必须切回去。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class SettingsViewModelTest {

    private val clDataStore = mockk<ClDataStore>()
    private val userRepository = mockk<UserRepository>()
    private val authRepository = mockk<AuthRepository>()

    private val reminder = MutableStateFlow(true)
    private val currentUser = MutableStateFlow<UserProfile?>(
        UserProfile(id = 111, gender = Gender.MALE, nickname = "男1", showInNearby = 1),
    )

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { clDataStore.reminderEnabled } returns reminder
        every { clDataStore.setReminderEnabled(any()) } just runs
        every { userRepository.currentUser } returns currentUser
        coEvery { userRepository.me() } returns ApiResult.Success(UserProfile(id = 111))
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = SettingsViewModel(clDataStore, userRepository, authRepository)

    // ---------- 消息提醒（本地开关） ----------

    @Test
    fun `消息提醒开关跟着本地读到的值`() {
        reminder.value = false

        assertFalse(viewModel().uiState.value.reminderEnabled)
    }

    @Test
    fun `切消息提醒只写本地，不打接口`() {
        val vm = viewModel()

        vm.onReminderChange(false)

        verify(exactly = 1) { clDataStore.setReminderEnabled(false) }
        coVerify(exactly = 0) { userRepository.updateSettings(any(), any()) }
    }

    // ---------- 展示给附近的人（服务端开关） ----------

    @Test
    fun `关掉展示给附近的人会调接口并刷新资料`() {
        coEvery { userRepository.updateSettings(any(), any()) } returns ApiResult.Success(Unit)
        val vm = viewModel()

        vm.onShowInNearbyChange(false)

        assertFalse(vm.uiState.value.showInNearby)
        coVerify(exactly = 1) { userRepository.updateSettings(showInNearby = 0, showInRecommend = null) }
        coVerify(exactly = 1) { userRepository.me() }
        assertEquals("已从同城列表隐藏", vm.uiState.value.toast)
    }

    @Test
    fun `服务端开关失败要切回来并提示`() {
        coEvery { userRepository.updateSettings(any(), any()) } returns ApiResult.BizError(40001, "当前状态不允许此操作")
        val vm = viewModel()

        vm.onShowInNearbyChange(false)

        assertTrue("失败了要切回去，不能骗用户", vm.uiState.value.showInNearby)
        assertEquals("当前状态不允许此操作", vm.uiState.value.toast)
        assertFalse(vm.uiState.value.switching)
    }

    @Test
    fun `网络失败也切回来`() {
        coEvery { userRepository.updateSettings(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()

        vm.onShowInNearbyChange(false)

        assertTrue(vm.uiState.value.showInNearby)
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    // ---------- 修改密码 ----------

    @Test
    fun `新密码不合规或两次不一致都不能提交`() {
        val vm = viewModel()
        vm.onPasswordSheetVisibleChange(true)

        vm.onOldPasswordChange("abc12345")
        vm.onNewPasswordChange("abc")                 // 太短
        vm.onConfirmPasswordChange("abc")
        assertFalse(vm.uiState.value.canSubmitPassword)
        assertNotNull("格式不对要给提示", vm.uiState.value.newPasswordHint)

        vm.onNewPasswordChange("xyz98765")
        vm.onConfirmPasswordChange("xyz98766")        // 不一致
        assertFalse(vm.uiState.value.canSubmitPassword)
        assertEquals("两次输入的密码不一致", vm.uiState.value.confirmHint)

        vm.onConfirmPasswordChange("xyz98765")
        assertTrue(vm.uiState.value.canSubmitPassword)
    }

    @Test
    fun `改密码成功关弹窗并清掉输入`() {
        coEvery { authRepository.changePassword(any(), any()) } returns ApiResult.Success(Unit)
        val vm = viewModel()
        vm.onPasswordSheetVisibleChange(true)
        vm.onOldPasswordChange("abc12345")
        vm.onNewPasswordChange("xyz98765")
        vm.onConfirmPasswordChange("xyz98765")

        vm.onSubmitPassword()

        assertFalse(vm.uiState.value.passwordSheetVisible)
        assertEquals("", vm.uiState.value.newPassword)
        assertEquals("密码已修改", vm.uiState.value.toast)
    }

    /** 10006 旧密码错：用服务端的文案，弹窗留着让人改。 */
    @Test
    fun `旧密码错时提示服务端的话且弹窗还在`() {
        coEvery { authRepository.changePassword(any(), any()) } returns ApiResult.BizError(10006, "旧密码错误")
        val vm = viewModel()
        vm.onPasswordSheetVisibleChange(true)
        vm.onOldPasswordChange("wrong1234")
        vm.onNewPasswordChange("xyz98765")
        vm.onConfirmPasswordChange("xyz98765")

        vm.onSubmitPassword()

        assertTrue(vm.uiState.value.passwordSheetVisible)
        assertEquals("旧密码错误", vm.uiState.value.passwordError)
        assertFalse(vm.uiState.value.submittingPassword)
    }

    @Test
    fun `关掉改密码弹窗会把输入清干净`() {
        val vm = viewModel()
        vm.onPasswordSheetVisibleChange(true)
        vm.onNewPasswordChange("xyz98765")

        vm.onPasswordSheetVisibleChange(false)

        assertEquals("", vm.uiState.value.newPassword)
        assertNull(vm.uiState.value.passwordError)
    }

    // ---------- 注销 ----------

    @Test
    fun `注销走的是 authRepository`() {
        coEvery { authRepository.cancelAccount() } returns ApiResult.Success(Unit)
        val vm = viewModel()
        vm.onCancelConfirmVisibleChange(true)

        vm.onConfirmCancelAccount()

        coVerify(exactly = 1) { authRepository.cancelAccount() }
        assertFalse(vm.uiState.value.cancelConfirmVisible)
    }

    @Test
    fun `注销失败把服务端的话提示出来`() {
        coEvery { authRepository.cancelAccount() } returns ApiResult.BizError(40001, "账号已注销，无法登录")
        val vm = viewModel()
        vm.onCancelConfirmVisibleChange(true)

        vm.onConfirmCancelAccount()

        assertEquals("账号已注销，无法登录", vm.uiState.value.toast)
        assertFalse(vm.uiState.value.cancelSubmitting)
    }

    // ---------- 退出登录 ----------

    @Test
    fun `退出登录调 authRepository`() {
        coEvery { authRepository.logout() } returns Unit
        val vm = viewModel()

        vm.onLogout()

        coVerify(exactly = 1) { authRepository.logout() }
    }
}
