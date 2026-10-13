package com.qichuang.changliao.feature.income

import com.qichuang.changliao.core.data.model.IncomeLevel
import com.qichuang.changliao.core.data.model.IncomeLevelRow
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.IncomeRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.every
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 我的等级与收费（P27）：等级进度、单价与分成的文案（含未实名说明）、在线接听开关。
 *
 * 开关是**乐观切换**：先本地切过去，失败再切回来并把服务端的话提示出来。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class IncomeLevelViewModelTest {

    private val incomeRepository = mockk<IncomeRepository>()
    private val userRepository = mockk<UserRepository>()

    private val currentUser = MutableStateFlow<UserProfile?>(UserProfile(id = 113, nickname = "晚晴", showInRecommend = 1))

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
        coEvery { incomeRepository.level() } returns ApiResult.Success(level())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = IncomeLevelViewModel(incomeRepository, userRepository).also { it.enter() }

    @Test
    fun `等级卡显示当前等级与进度`() {
        val state = viewModel().uiState.value

        assertEquals("Lv3", state.levelText)
        assertEquals("120000 积分", state.earnedText)
        assertEquals(0.1f, state.progress, 0.001f)
        assertEquals("距离 Lv3 的下一级还差 180000 积分", state.progressText)
    }

    @Test
    fun `单价和分成按万分比显示`() {
        val state = viewModel().uiState.value

        assertEquals("10 币/条", state.messagePriceText)
        assertEquals("20%", state.shareText)
        assertEquals("34%", state.giftShareText)
    }

    /** `docs/01` P27：未实名时要说明「完成实名认证后分成比例为 X%，当前为 Y%」。 */
    @Test
    fun `未实名时提示实名后的分成比例`() {
        val state = viewModel().uiState.value

        assertTrue(state.showRealNameHint)
        assertEquals("完成实名认证后分成比例为 40%，当前为 20%", state.shareHint)
    }

    @Test
    fun `已实名时不再提示`() {
        coEvery { incomeRepository.level() } returns ApiResult.Success(level().copy(realNamePassed = true, actualShareRate = 4000))

        val state = viewModel().uiState.value

        assertFalse(state.showRealNameHint)
        assertEquals("", state.shareHint)
        assertEquals("40%", state.shareText)
    }

    @Test
    fun `开关打开时才有语音视频单价`() {
        assertFalse(viewModel().uiState.value.callEnabled)

        coEvery { incomeRepository.level() } returns ApiResult.Success(level().copy(callEnabled = true))
        val state = viewModel().uiState.value

        assertTrue(state.callEnabled)
        assertEquals("60 币/分钟", state.voicePriceText)
        assertEquals("120 币/分钟", state.videoPriceText)
    }

    @Test
    fun `开关的初始值跟着当前登录用户`() {
        currentUser.value = UserProfile(id = 113, nickname = "晚晴", showInRecommend = 0)

        assertFalse(viewModel().uiState.value.showInRecommend)
    }

    @Test
    fun `关闭在线接听调的是用户设置接口`() {
        val vm = viewModel()
        coEvery { userRepository.updateSettings(any(), any()) } returns ApiResult.Success(Unit)

        vm.onToggleOnline(false)

        coVerify(exactly = 1) { userRepository.updateSettings(showInNearby = null, showInRecommend = 0) }
        assertFalse(vm.uiState.value.showInRecommend)
        assertEquals("已关闭在线接听", vm.uiState.value.toast)
    }

    @Test
    fun `切换失败要切回来并提示`() {
        val vm = viewModel()
        coEvery { userRepository.updateSettings(any(), any()) } returns ApiResult.BizError(40001, "当前状态不允许此操作")

        vm.onToggleOnline(false)

        assertTrue("失败了要切回去，不能骗用户", vm.uiState.value.showInRecommend)
        assertEquals("当前状态不允许此操作", vm.uiState.value.toast)
        assertFalse(vm.uiState.value.switching)
    }

    @Test
    fun `网络失败也切回来`() {
        val vm = viewModel()
        coEvery { userRepository.updateSettings(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        vm.onToggleOnline(false)

        assertTrue(vm.uiState.value.showInRecommend)
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `拉不到时整页报错，重试再拉一次`() {
        coEvery { incomeRepository.level() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { incomeRepository.level() } returns ApiResult.Success(level())
        vm.onRetry()

        assertEquals("Lv3", vm.uiState.value.levelText)
    }

    private fun level() = IncomeLevel(
        level = 3,
        name = "Lv3",
        totalEarnedPoints = 120000,
        nextLevel = 4,
        nextThreshold = 300000,
        pointsToNext = 180000,
        progressPercent = 10,
        messagePrice = 10,
        shareRate = 4000,
        actualShareRate = 2000,
        giftShareRate = 3400,
        realNamePassed = false,
        callEnabled = false,
        voicePrice = 60,
        videoPrice = 120,
        levels = listOf(IncomeLevelRow(level = 1, name = "Lv1", thresholdPoints = 0, messagePrice = 5, messageShareRate = 3000)),
    )
}
