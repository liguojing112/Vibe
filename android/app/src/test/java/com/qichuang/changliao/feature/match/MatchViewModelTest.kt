package com.qichuang.changliao.feature.match

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.CallKind
import com.qichuang.changliao.core.data.model.DiscoverPage
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import io.mockk.coEvery
import io.mockk.every
import io.mockk.mockk
import io.mockk.verify
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Before
import org.junit.Test

/**
 * 速配 / 语音大厅（docs/01 P02、P16）。
 *
 * 两个页面共用一个 ViewModel，靠 `kind` 区分 —— 所以这里最要紧的是
 * **`kind` 真的决定了请求哪个 Tab、显示哪一栏单价**。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class MatchViewModelTest {

    private val discoverRepository = mockk<DiscoverRepository>()
    private val userRepository = mockk<UserRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()

    private val currentUser = MutableStateFlow<UserProfile?>(null)
    private val appConfig = MutableStateFlow<AppConfig?>(null)

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
        every { appConfigRepository.config } returns appConfig
        // 进页面会真拉一次 `/user/me`（T9.1-fix1：余额是钱，不能吃缓存）
        coEvery { userRepository.me() } returns ApiResult.Success(maleProfile())
        appConfig.value = AppConfig(callEnabled = true, voicePriceMin = 60, videoPriceMin = 120)
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun maleProfile() = UserProfile(id = 7, gender = Gender.MALE, nickname = "我", coinBalance = 360)

    private fun user(id: Long, voice: Long? = 60, video: Long? = 120) = DiscoverUser(
        id = id,
        nickname = "用户$id",
        avatar = "https://cdn/$id.png",
        city = "上海",
        age = 26,
        job = "设计师",
        online = true,
        messagePrice = 5,
        voicePrice = voice,
        videoPrice = video,
    )

    private fun stubPage(tab: String, result: ApiResult<DiscoverPage>) {
        every { discoverRepository.firstPage(tab, 7, any(), any()) } returns flowOf(result)
    }

    private fun viewModel() = MatchViewModel(discoverRepository, userRepository, appConfigRepository)

    @Test
    fun `视频速配拿 video Tab 的列表和视频示例价`() {
        currentUser.value = maleProfile()
        stubPage("video", ApiResult.Success(DiscoverPage(list = listOf(user(1), user(2)), total = 2)))

        val viewModel = viewModel()
        viewModel.enter(CallKind.VIDEO)

        val state = viewModel.uiState.value
        assertEquals(CallKind.VIDEO, state.kind)
        assertEquals(2, state.users.size)
        assertEquals("360", state.balanceAmount)
        assertEquals(120, state.priceMin)
        verify { discoverRepository.firstPage("video", 7, any(), any()) }
    }

    /** 语音大厅是同一个接口的另一面：请求 `voice` Tab、用语音单价。 */
    @Test
    fun `语音大厅拿 voice Tab 的列表和语音示例价`() {
        currentUser.value = maleProfile()
        stubPage("voice", ApiResult.Success(DiscoverPage(list = listOf(user(1)), total = 1)))

        val viewModel = viewModel()
        viewModel.enter(CallKind.VOICE)

        val state = viewModel.uiState.value
        assertEquals(CallKind.VOICE, state.kind)
        assertEquals(1, state.users.size)
        assertEquals(60, state.priceMin)
        verify { discoverRepository.firstPage("voice", 7, any(), any()) }
    }

    @Test
    fun `女用户看语音大厅显示积分`() {
        val female = UserProfile(id = 8, gender = Gender.FEMALE, nickname = "我", pointBalance = 1200)
        coEvery { userRepository.me() } returns ApiResult.Success(female)
        currentUser.value = female
        stubPage("voice", ApiResult.Success(DiscoverPage()))

        val viewModel = viewModel()
        viewModel.enter(CallKind.VOICE)

        assertEquals("1200", viewModel.uiState.value.balanceAmount)
        assertEquals("积分", viewModel.uiState.value.balanceUnit)
    }

    /** **T9.1-fix1**：和 P01 一样，速配/语音大厅也要每次真拉一次「我」，不吃缓存。 */
    @Test
    fun `余额取服务端实时值而不是缓存`() {
        currentUser.value = maleProfile().copy(coinBalance = 0)
        coEvery { userRepository.me() } returns ApiResult.Success(maleProfile().copy(coinBalance = 100))
        stubPage("video", ApiResult.Success(DiscoverPage()))

        val viewModel = viewModel()
        viewModel.enter(CallKind.VIDEO)

        assertEquals("100", viewModel.uiState.value.balanceAmount)
    }

    @Test
    fun `没有人时是空态而不是错误`() {
        currentUser.value = maleProfile()
        stubPage("video", ApiResult.Success(DiscoverPage()))

        val viewModel = viewModel()
        viewModel.enter(CallKind.VIDEO)

        val state = viewModel.uiState.value
        assertEquals(0, state.users.size)
        assertNull(state.error)
        assertEquals(true, state.isEmpty)
    }

    @Test
    fun `出错时给整页错误`() {
        currentUser.value = maleProfile()
        stubPage("video", ApiResult.BizError(500, "服务器开小差了"))

        val viewModel = viewModel()
        viewModel.enter(CallKind.VIDEO)

        assertEquals("服务器开小差了", viewModel.uiState.value.error)
    }
}
