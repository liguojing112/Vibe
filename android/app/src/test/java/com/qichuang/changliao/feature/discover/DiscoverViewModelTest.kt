package com.qichuang.changliao.feature.discover

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.DiscoverPage
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.DiscoverRepository
import com.qichuang.changliao.core.navigation.DiscoverTabIntent
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
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
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 发现页（docs/01 P01）：先缓存后刷新、切 Tab、男/女余额、分页与失败。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class DiscoverViewModelTest {

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
        coEvery { discoverRepository.filter(any()) } returns DiscoverFilter()
        coEvery { userRepository.me() } returns ApiResult.Success(maleProfile())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun maleProfile() = UserProfile(
        id = 7,
        gender = Gender.MALE,
        nickname = "我",
        coinBalance = 360,
    )

    private fun femaleProfile() = UserProfile(
        id = 8,
        gender = Gender.FEMALE,
        nickname = "我",
        pointBalance = 1200,
    )

    private fun user(id: Long, price: Long? = 5) = DiscoverUser(
        id = id,
        nickname = "用户$id",
        avatar = "https://cdn/$id.png",
        city = "上海",
        age = 26,
        job = "设计师",
        bio = "喜欢逛展",
        online = true,
        isNew = true,
        verified = true,
        messagePrice = price,
    )

    private fun page(users: List<DiscoverUser>, hasMore: Boolean = false) = DiscoverPage(
        list = users,
        total = users.size,
        page = 1,
        hasMore = hasMore,
    )

    private fun stubFirstPage(
        page: DiscoverPage,
        tab: String = "recommend",
        userId: Long = 7,
        force: Boolean = false,
    ) {
        every {
            discoverRepository.firstPage(tab, userId, any(), force)
        } returns flowOf(ApiResult.Success(page))
    }

    private fun viewModel() = DiscoverViewModel(discoverRepository, userRepository, DiscoverTabIntent(), appConfigRepository)

    // ---------- P8：语音/视频 Tab 的显隐 ----------

    /** 开关关着时只有三个 Tab —— P8 的验收要求「开关关闭时所有入口都不显示」。 */
    @Test
    fun `开关关着时没有视频和语音 Tab`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()

        assertEquals(listOf(DiscoverTab.RECOMMEND, DiscoverTab.NEW, DiscoverTab.CITY), viewModel.uiState.value.tabs)
        assertFalse(viewModel.uiState.value.callEnabled)
    }

    @Test
    fun `开关打开时多出视频和语音 Tab`() {
        currentUser.value = maleProfile()
        appConfig.value = AppConfig(callEnabled = true, voicePriceMin = 60, videoPriceMin = 120)
        stubFirstPage(page(listOf(user(1))), tab = "video")

        val viewModel = viewModel()

        assertEquals(5, viewModel.uiState.value.tabs.size)
        assertTrue(DiscoverTab.VIDEO in viewModel.uiState.value.tabs)
        assertTrue(DiscoverTab.VOICE in viewModel.uiState.value.tabs)
        // 快捷卡上的「示例价格」也是从启动配置来的
        assertEquals(60, viewModel.uiState.value.voicePriceMin)
        assertEquals(120, viewModel.uiState.value.videoPriceMin)
    }

    /** 切到「视频」Tab 会按 `tab=video` 去请求（服务端只给「现在能接」的人）。 */
    @Test
    fun `切到视频 Tab 用 video 参数请求`() {
        currentUser.value = maleProfile()
        appConfig.value = AppConfig(callEnabled = true)
        stubFirstPage(page(emptyList()))
        stubFirstPage(page(emptyList()), tab = "video")

        val viewModel = viewModel()
        // 先 enter 一次拿到 userId（没进过页面时 userId 还是 0，切 Tab 会直接返回）
        viewModel.enter()
        viewModel.onTabSelected(DiscoverTab.VIDEO)

        verify { discoverRepository.firstPage("video", 7, any(), true) }
    }

    /** 开关**关掉**时如果正停在「语音」Tab 上，要退回「推荐」—— 不能停在一个不该存在的 Tab 上。 */
    @Test
    fun `开关关掉后从语音 Tab 退回推荐`() {
        currentUser.value = maleProfile()
        appConfig.value = AppConfig(callEnabled = true)
        stubFirstPage(page(emptyList()))
        stubFirstPage(page(emptyList()), tab = "voice")

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onTabSelected(DiscoverTab.VOICE)
        assertEquals(DiscoverTab.VOICE, viewModel.uiState.value.tab)

        appConfig.value = AppConfig(callEnabled = false)

        assertEquals(DiscoverTab.RECOMMEND, viewModel.uiState.value.tab)
        assertEquals(3, viewModel.uiState.value.tabs.size)
    }

    /** 开关关着时有人请求切「语音」Tab（例如旧的深链）要被忽略，不能把页面切过去。 */
    @Test
    fun `开关关着时切语音 Tab 被忽略`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onTabSelected(DiscoverTab.VOICE)

        assertEquals(DiscoverTab.RECOMMEND, viewModel.uiState.value.tab)
    }


    @Test
    fun `进入页面拿到列表和余额`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1), user(2))))

        val viewModel = viewModel()
        viewModel.enter()

        val state = viewModel.uiState.value
        assertEquals(2, state.users.size)
        assertEquals("360", state.balanceAmount)
        assertEquals("币", state.balanceUnit)
        assertFalse(state.loading)
        assertNull(state.error)
    }

    @Test
    fun `女用户显示积分`() {
        coEvery { userRepository.me() } returns ApiResult.Success(femaleProfile())
        currentUser.value = femaleProfile()
        stubFirstPage(page(listOf(user(1))), userId = 8)

        val viewModel = viewModel()
        viewModel.enter()

        val state = viewModel.uiState.value
        assertEquals("1200", state.balanceAmount)
        assertEquals("积分", state.balanceUnit)
    }

    /**
     * **T9.1-fix1**：余额是钱，每次进页面都要真拉一次 `/user/me`。
     *
     * 原来的写法是「有缓存就用缓存」，于是客服在后台刚充完值、用户正停在 P01 上时，
     * 顶部胶囊一直显示旧数字，**下拉刷新也不动**（那只重拉列表）。
     * 走查时的表现：后台充了 100，P01 还是 0，切到「我的」再回来才变 100。
     */
    @Test
    fun `每次进页面都真拉一次我的资料`() {
        // 缓存里是旧余额（0），服务端已经是 100
        currentUser.value = maleProfile().copy(coinBalance = 0)
        coEvery { userRepository.me() } returns ApiResult.Success(maleProfile().copy(coinBalance = 100))
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()
        viewModel.enter()

        assertEquals("100", viewModel.uiState.value.balanceAmount)
        coVerify(exactly = 1) { userRepository.me() }
    }

    /** 拉「我」失败但有缓存时不整页报错（页面照常可用，只是余额是旧值）。 */
    @Test
    fun `我的资料拉失败但有缓存时页面照常可用`() {
        currentUser.value = maleProfile()
        coEvery { userRepository.me() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()
        viewModel.enter()

        val state = viewModel.uiState.value
        assertNull(state.error)
        assertEquals("360", state.balanceAmount)
    }

    /** 下拉刷新也要把「我」重拉一遍（用户最自然的「刷新」手势得能刷出余额）。 */
    @Test
    fun `下拉刷新会重拉我的资料`() {
        currentUser.value = maleProfile().copy(coinBalance = 0)
        coEvery { userRepository.me() } returnsMany listOf(
            ApiResult.Success(maleProfile().copy(coinBalance = 0)),
            ApiResult.Success(maleProfile().copy(coinBalance = 100)),
        )
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()
        viewModel.enter()
        assertEquals("0", viewModel.uiState.value.balanceAmount)

        viewModel.onRefresh()

        assertEquals("100", viewModel.uiState.value.balanceAmount)
        coVerify(exactly = 2) { userRepository.me() }
    }

    @Test
    fun `没有缓存时先转圈后出内容`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()
        assertTrue(viewModel.uiState.value.loading)

        viewModel.enter()

        assertFalse(viewModel.uiState.value.loading)
        assertEquals(1, viewModel.uiState.value.users.size)
    }

    @Test
    fun `拿不到缓存时整页报错可重试`() {
        currentUser.value = maleProfile()
        every {
            discoverRepository.firstPage("recommend", 7, any(), false)
        } returns flowOf(ApiResult.NetworkError(NETWORK_ERROR_MESSAGE))

        val viewModel = viewModel()
        viewModel.enter()

        val state = viewModel.uiState.value
        assertEquals(NETWORK_ERROR_MESSAGE, state.error)
        assertFalse(state.loading)
        assertTrue(state.users.isEmpty())
        // 有错误时不算「空状态」—— 页面先走错误分支
        assertFalse(state.isEmpty)
    }

    @Test
    fun `我自己的资料拿不到时不发列表请求`() {
        coEvery { userRepository.me() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val viewModel = viewModel()
        viewModel.enter()

        assertEquals(NETWORK_ERROR_MESSAGE, viewModel.uiState.value.error)
        verify(exactly = 0) { discoverRepository.firstPage(any(), any(), any(), any()) }
    }

    @Test
    fun `切 Tab 立刻按新 Tab 请求`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))
        every {
            discoverRepository.firstPage("city", 7, any(), true)
        } returns flowOf(ApiResult.Success(page(listOf(user(9)))))

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onTabSelected(DiscoverTab.CITY)

        val state = viewModel.uiState.value
        assertEquals(DiscoverTab.CITY, state.tab)
        assertEquals(9L, state.users.first().id)
    }

    @Test
    fun `重复点同一个 Tab 不重复请求`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))
        val viewModel = viewModel()
        viewModel.enter()

        viewModel.onTabSelected(DiscoverTab.RECOMMEND)

        // enter() 一次 + 切 Tab 没有发生
        verify(exactly = 1) { discoverRepository.firstPage(any(), any(), any(), any()) }
    }

    @Test
    fun `下拉刷新走 force`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))
        every {
            discoverRepository.firstPage("recommend", 7, any(), true)
        } returns flowOf(ApiResult.Success(page(listOf(user(1)), hasMore = true)))

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onRefresh()

        verify { discoverRepository.firstPage("recommend", 7, any(), true) }
    }

    @Test
    fun `上拉加载把下一页接在后面`() {
        currentUser.value = maleProfile()
        stubFirstPage(page((1L..20L).map { user(it) }, hasMore = true))
        coEvery { discoverRepository.more("recommend", any(), 2) } returns
            ApiResult.Success(page(listOf(user(21))))

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onLoadMore()

        val state = viewModel.uiState.value
        assertEquals(21, state.users.size)
        assertFalse(state.loadingMore)
        coVerify { discoverRepository.more("recommend", any(), 2) }
    }

    @Test
    fun `没有更多时不再请求`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1)), hasMore = false))

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onLoadMore()

        coVerify(exactly = 0) { discoverRepository.more(any(), any(), any()) }
    }

    @Test
    fun `加载更多失败只显示在列表底部`() {
        currentUser.value = maleProfile()
        stubFirstPage(page((1L..20L).map { user(it) }, hasMore = true))
        coEvery { discoverRepository.more(any(), any(), any()) } returns
            ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val viewModel = viewModel()
        viewModel.enter()
        viewModel.onLoadMore()

        val state = viewModel.uiState.value
        assertEquals(NETWORK_ERROR_MESSAGE, state.loadMoreError)
        // 整页错误不能出现，已有的 20 条还要在
        assertNull(state.error)
        assertEquals(20, state.users.size)
    }

    @Test
    fun `筛选改动后回到页面会重新请求`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))
        val filter = DiscoverFilter(city = "杭州")
        coEvery { discoverRepository.filter(7) } returns filter
        every {
            discoverRepository.firstPage("recommend", 7, filter, true)
        } returns flowOf(ApiResult.Success(page(listOf(user(5)))))

        val viewModel = viewModel()
        viewModel.enter()

        assertEquals("杭州", viewModel.uiState.value.filter.city)
        assertEquals(5L, viewModel.uiState.value.users.first().id)
    }

    @Test
    fun `筛选没改动时不强制刷新`() {
        currentUser.value = maleProfile()
        stubFirstPage(page(listOf(user(1))))

        val viewModel = viewModel()
        viewModel.enter()

        verify { discoverRepository.firstPage("recommend", 7, any(), false) }
    }
}
