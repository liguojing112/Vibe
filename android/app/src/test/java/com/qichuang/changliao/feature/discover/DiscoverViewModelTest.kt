package com.qichuang.changliao.feature.discover

import com.qichuang.changliao.core.data.model.DiscoverFilter
import com.qichuang.changliao.core.data.model.DiscoverPage
import com.qichuang.changliao.core.data.model.DiscoverUser
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.UserProfile
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

    private val currentUser = MutableStateFlow<UserProfile?>(null)

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
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

    private fun viewModel() = DiscoverViewModel(discoverRepository, userRepository, DiscoverTabIntent())

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
        currentUser.value = femaleProfile()
        stubFirstPage(page(listOf(user(1))), userId = 8)

        val viewModel = viewModel()
        viewModel.enter()

        val state = viewModel.uiState.value
        assertEquals("1200", state.balanceAmount)
        assertEquals("积分", state.balanceUnit)
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
