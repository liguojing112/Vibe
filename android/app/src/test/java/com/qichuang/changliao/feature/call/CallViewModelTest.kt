package com.qichuang.changliao.feature.call

import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserDetail
import com.qichuang.changliao.core.data.model.UserDetailPrice
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.every
import io.mockk.mockk
import io.mockk.verify
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 拨打前价格确认（docs/01 P17）。
 *
 * 这里最要紧的一条是 **`01` 第 4 节点的名**：
 * 「在 P17 点『确认拨打』时，弹出提示『功能暂未开放』，**不发请求、不扣币**」——
 * 所以 `确认拨打只弹提示不发任何请求` 这条用例是这个任务的底线，
 * 以后谁往 `onConfirm()` 里加了个「先占位」的接口调用，它会立刻红。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class CallViewModelTest {

    private val userRepository = mockk<UserRepository>()

    private val currentUser = MutableStateFlow<UserProfile?>(null)

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun maleProfile() = UserProfile(id = 7, gender = Gender.MALE, nickname = "我", coinBalance = 360)

    private fun peer(
        voicePrice: Long? = 60,
        videoPrice: Long? = 120,
    ) = UserDetail(
        id = 113,
        nickname = "晚晴",
        avatar = "https://cdn/113.png",
        online = true,
        verified = true,
        price = UserDetailPrice(messagePrice = 10, voicePrice = voicePrice, videoPrice = videoPrice),
    )

    private fun viewModel() = CallViewModel(userRepository)

    // ---------- 底线：不点钱、不发请求 ----------

    @Test
    fun `确认拨打只弹提示不发任何请求`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.Success(peer())

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)

        viewModel.onConfirm()

        assertEquals("功能暂未开放", viewModel.uiState.value.toast)
        // 从头到尾**只调过一次** `detail`（进页面加载对方资料那一次）。
        // onConfirm 要是偷偷发起了呼叫，这里就会变成 2。
        coVerify(exactly = 1) { userRepository.detail(113) }
        // 余额也没被动过（ViewModel 里根本没有改余额的入口，这条是防以后加错）
        assertEquals(360, viewModel.uiState.value.balance)
    }

    @Test
    fun `提示读掉之后不会再弹`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.Success(peer())

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)
        viewModel.onConfirm()
        viewModel.onToastShown()

        assertNull(viewModel.uiState.value.toast)
    }

    /** 同一个人重复进（P19 返回 P17）不该反复拉资料。 */
    @Test
    fun `重复进入同一个人不会重复请求`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.Success(peer())

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)
        viewModel.enter(113, CallKind.VIDEO)

        coVerify(exactly = 1) { userRepository.detail(113) }
    }

    // ---------- 页面上的数字 ----------

    @Test
    fun `视频通话取视频单价，最多可聊按余额算`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.Success(peer())

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)

        val state = viewModel.uiState.value
        assertEquals("晚晴", state.peerName)
        assertEquals(120, state.price)
        assertEquals("1 对 1 视频通话", state.callTypeText)
        assertEquals("约 3 分钟", state.maxMinutesText) // 360 / 120
        assertEquals("120 币/分钟", state.confirmSubText)
        assertTrue(state.peerOnline)
        assertTrue(state.peerVerified)
    }

    @Test
    fun `语音通话取语音单价`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.Success(peer())

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VOICE)

        val state = viewModel.uiState.value
        assertEquals(60, state.price)
        assertEquals("1 对 1 语音通话", state.callTypeText)
        assertEquals("约 6 分钟", state.maxMinutesText) // 360 / 60
    }

    /** 开关关着时服务端不返回这两项 —— 页面要给「—」而不是「约 0 分钟」。 */
    @Test
    fun `没有单价时最多可聊显示破折号`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.Success(
            peer(voicePrice = null, videoPrice = null),
        )

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)

        val state = viewModel.uiState.value
        assertEquals(0, state.price)
        assertEquals("—", state.maxMinutesText)
        assertEquals("", state.confirmSubText)
    }

    @Test
    fun `对方资料拿不到时给整页错误`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.detail(113) } returns ApiResult.BizError(404, "用户不存在")

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)

        assertEquals("用户不存在", viewModel.uiState.value.error)
        assertEquals(false, viewModel.uiState.value.loading)
    }

    @Test
    fun `没有缓存的资料时会拉一次我的信息拿余额`() {
        currentUser.value = null
        coEvery { userRepository.me() } returns ApiResult.Success(maleProfile())
        coEvery { userRepository.detail(113) } returns ApiResult.Success(peer())

        val viewModel = viewModel()
        viewModel.enter(113, CallKind.VIDEO)

        assertEquals(360, viewModel.uiState.value.balance)
        coVerify(exactly = 1) { userRepository.me() }
    }
}
