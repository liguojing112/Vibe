package com.qichuang.changliao.feature.relation

import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.RelationKind
import com.qichuang.changliao.core.data.model.RelationPage
import com.qichuang.changliao.core.data.model.RelationPerson
import com.qichuang.changliao.core.data.model.RelationState
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.RelationRepository
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
 * 最近访客（P08）与喜欢我的人（P09）：先缓存后刷新、非会员打码、回关、分页。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class RelationListViewModelTest {

    private val relationRepository = mockk<RelationRepository>()
    private val userRepository = mockk<UserRepository>()

    private val currentUser = MutableStateFlow<UserProfile?>(
        UserProfile(id = 7, gender = Gender.MALE, nickname = "我"),
    )

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun person(
        id: Long,
        nickname: String = "用户$id",
        mutual: Boolean = false,
        visitedAt: String? = null,
    ) = RelationPerson(
        id = id,
        nickname = nickname,
        avatar = "https://cdn/$id.png",
        city = "上海",
        age = 26,
        isFollowing = false,
        isMutual = mutual,
        visitedAt = visitedAt,
    )

    private fun page(
        people: List<RelationPerson>,
        total: Int = people.size,
        hasMore: Boolean = false,
        locked: Boolean = false,
    ) = RelationPage(list = people, total = total, page = 1, hasMore = hasMore, locked = locked)

    private fun stubFirstPage(
        kind: RelationKind,
        page: RelationPage,
        force: Boolean = false,
        userId: Long = 7,
    ) {
        every { relationRepository.firstPage(kind, userId, force) } returns flowOf(ApiResult.Success(page))
    }

    private fun viewModel() = RelationListViewModel(relationRepository, userRepository)

    private fun loaded(kind: RelationKind = RelationKind.VISITORS, page: RelationPage = page(listOf(person(1)))): RelationListViewModel {
        stubFirstPage(kind, page)
        val viewModel = viewModel()
        viewModel.enter(kind)

        return viewModel
    }

    // ---------- 加载 ----------

    @Test
    fun `进入页面拿到访客列表和总数`() {
        val state = loaded(
            page = page(listOf(person(1, visitedAt = "2026-10-12 09:00:00")), total = 6),
        ).uiState.value

        assertFalse(state.loading)
        assertEquals(1, state.people.size)
        assertEquals(6, state.total)
        assertEquals("有 6 人看过你的主页 ✨", state.noticeTitle)
        assertNull(state.noticeContent)
    }

    @Test
    fun `喜欢我的人的提示卡文案不一样`() {
        val state = loaded(RelationKind.FANS, page(listOf(person(1)), total = 3)).uiState.value

        assertEquals("有 3 人喜欢你 ✨", state.noticeTitle)
        assertEquals("喜欢我的人", state.title)
    }

    @Test
    fun `没人看时不给提示卡`() {
        val state = loaded(page = page(emptyList(), total = 0)).uiState.value

        assertTrue(state.isEmpty)
        assertNull(state.noticeTitle)
    }

    @Test
    fun `加载失败整页可重试`() {
        every { relationRepository.firstPage(RelationKind.VISITORS, 7, false) } returns
            flowOf(ApiResult.NetworkError(NETWORK_ERROR_MESSAGE))

        val viewModel = viewModel()
        viewModel.enter(RelationKind.VISITORS)

        assertEquals(NETWORK_ERROR_MESSAGE, viewModel.uiState.value.error)
    }

    // ---------- 打码 ----------

    @Test
    fun `非会员拿到打码数据`() {
        val state = loaded(
            page = page(listOf(person(1, nickname = "小*")), total = 6, locked = true),
        ).uiState.value

        assertTrue(state.locked)
        assertEquals("小*", state.people.first().nickname)
        assertEquals("开通会员查看全部", state.noticeContent)
    }

    @Test
    fun `打码时回关引导去开通会员`() {
        val viewModel = loaded(page = page(listOf(person(1)), locked = true))

        viewModel.onFollowBack(viewModel.uiState.value.people.first())

        assertTrue(viewModel.uiState.value.vipPromptVisible)
        coVerify(exactly = 0) { relationRepository.follow(any()) }
    }

    // ---------- 回关（P09） ----------

    @Test
    fun `回关之后按钮该变成查看`() {
        val fans = page(listOf(person(1, nickname = "小满")))
        val viewModel = loaded(RelationKind.FANS, fans)
        coEvery { relationRepository.follow(1) } returns ApiResult.Success(
            RelationState(isFollowing = true, isMutual = true),
        )

        viewModel.onFollowBack(viewModel.uiState.value.people.first())

        val updated = viewModel.uiState.value.people.first()
        assertTrue(updated.isFollowing)
        assertTrue(updated.isMutual)
        assertEquals("查看", relationActionLabel(RelationKind.FANS, updated))
    }

    @Test
    fun `回关前是回关按钮`() {
        val state = loaded(RelationKind.FANS, page(listOf(person(1)))).uiState.value

        assertEquals("回关", relationActionLabel(RelationKind.FANS, state.people.first()))
        // 访客列表永远是「查看」
        assertEquals("查看", relationActionLabel(RelationKind.VISITORS, person(1)))
    }

    @Test
    fun `回关失败弹服务端文案`() {
        val viewModel = loaded(RelationKind.FANS, page(listOf(person(1))))
        coEvery { relationRepository.follow(1) } returns ApiResult.BizError(30001, "对方不存在或已注销")

        viewModel.onFollowBack(viewModel.uiState.value.people.first())

        assertEquals("对方不存在或已注销", viewModel.uiState.value.toast)
        assertFalse(viewModel.uiState.value.people.first().isFollowing)
    }

    // ---------- 分页 ----------

    @Test
    fun `上拉加载接下一页`() {
        val first = page((1L..20L).map { person(it) }, total = 25, hasMore = true)
        val viewModel = loaded(page = first)
        coEvery { relationRepository.more(RelationKind.VISITORS, 2) } returns
            ApiResult.Success(page((21L..25L).map { person(it) }, total = 25))

        viewModel.onLoadMore()

        assertEquals(25, viewModel.uiState.value.people.size)
        assertFalse(viewModel.uiState.value.loadingMore)
    }

    @Test
    fun `没有更多时不再请求`() {
        val viewModel = loaded(page = page(listOf(person(1)), hasMore = false))

        viewModel.onLoadMore()

        coVerify(exactly = 0) { relationRepository.more(any(), any()) }
    }

    @Test
    fun `加载更多失败只显示在列表底部`() {
        val first = page((1L..20L).map { person(it) }, hasMore = true)
        val viewModel = loaded(page = first)
        coEvery { relationRepository.more(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        viewModel.onLoadMore()

        val state = viewModel.uiState.value
        assertEquals(NETWORK_ERROR_MESSAGE, state.loadMoreError)
        assertNull(state.error)
        assertEquals(20, state.people.size)
    }

    // ---------- 说明文字 ----------

    @Test
    fun `访客的说明带日期和城市`() {
        // 「今天」的判断依赖运行时的当前时间，所以直接用今天的时间戳
        val today = com.qichuang.changliao.core.common.TimeFormat
            .dateTime(System.currentTimeMillis() / 1000)

        val desc = relationDesc(RelationKind.VISITORS, person(1, visitedAt = today))

        assertEquals("今天看了你的资料 · 上海", desc)
    }

    @Test
    fun `访客没有时间时只说看了资料`() {
        assertEquals("看了你的资料 · 上海", relationDesc(RelationKind.VISITORS, person(1)))
    }

    @Test
    fun `喜欢我的人的说明是关注了你`() {
        assertEquals("关注了你 · 上海", relationDesc(RelationKind.FANS, person(1)))
    }
}
