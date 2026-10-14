package com.qichuang.changliao.feature.notice

import com.qichuang.changliao.core.data.model.Notice
import com.qichuang.changliao.core.data.model.NoticeCategory
import com.qichuang.changliao.core.data.model.NoticePage
import com.qichuang.changliao.core.data.repository.NoticeRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
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
 * 系统消息（P10）：两个分类 Tab、空状态、加载更多。
 *
 * 两个 Tab 的已读是**分开的**（服务端按 category 标），所以切 Tab 必须重新拉一次。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class NoticeViewModelTest {

    private val repository = mockk<NoticeRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { repository.list(any(), any()) } returns ApiResult.Success(NoticePage(list = notices(2), total = 2))
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = NoticeViewModel(repository).also { it.enter() }

    @Test
    fun `进页面拉的是系统通知`() {
        val state = viewModel().uiState.value

        assertEquals(NoticeCategory.SYSTEM, state.category)
        assertEquals("系统通知", state.title)
        assertEquals(2, state.notices.size)
        assertFalse(state.loading)
    }

    @Test
    fun `从互动通知进来时直接拉互动分类`() {
        val vm = NoticeViewModel(repository)

        vm.enter(NoticeCategory.INTERACT)

        assertEquals(NoticeCategory.INTERACT, vm.uiState.value.category)
        assertEquals("互动", vm.uiState.value.title)
        coVerify(exactly = 1) { repository.list(NoticeCategory.INTERACT, 1) }
    }

    @Test
    fun `切到互动 Tab 会重新拉一次`() {
        val vm = viewModel()

        vm.onCategoryChange(NoticeCategory.INTERACT)

        assertEquals(NoticeCategory.INTERACT, vm.uiState.value.category)
        coVerify(exactly = 1) { repository.list(NoticeCategory.INTERACT, 1) }
    }

    @Test
    fun `点已经选中的 Tab 不重复拉`() {
        val vm = viewModel()

        vm.onCategoryChange(NoticeCategory.SYSTEM)

        coVerify(exactly = 1) { repository.list(any(), any()) }
    }

    @Test
    fun `没有消息时给空状态`() {
        coEvery { repository.list(any(), any()) } returns ApiResult.Success(NoticePage(list = emptyList(), total = 0))

        assertTrue(viewModel().uiState.value.isEmpty)
    }

    @Test
    fun `满一页时能加载更多`() {
        coEvery { repository.list(NoticeCategory.SYSTEM, 1) } returns ApiResult.Success(
            NoticePage(list = notices(NoticeUiState.PAGE_SIZE), total = 25),
        )
        coEvery { repository.list(NoticeCategory.SYSTEM, 2) } returns ApiResult.Success(
            NoticePage(list = notices(5), total = 25),
        )

        val vm = viewModel()
        assertTrue(vm.uiState.value.hasMore)

        vm.onLoadMore()

        assertEquals(NoticeUiState.PAGE_SIZE + 5, vm.uiState.value.notices.size)
        assertEquals(2, vm.uiState.value.page)
        assertFalse(vm.uiState.value.hasMore)
    }

    @Test
    fun `未读数用服务端返回的那份`() {
        coEvery { repository.list(any(), any()) } returns ApiResult.Success(
            NoticePage(list = notices(1), total = 1, unread = 3),
        )

        assertEquals(3, viewModel().uiState.value.unread)
    }

    @Test
    fun `拉不到时整页报错，重试再拉一次`() {
        coEvery { repository.list(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { repository.list(any(), any()) } returns ApiResult.Success(NoticePage(list = notices(1), total = 1))
        vm.onRetry()

        assertNull(vm.uiState.value.error)
        assertEquals(1, vm.uiState.value.notices.size)
    }

    private fun notices(count: Int) = List(count) { index ->
        Notice(
            id = (index + 1).toLong(),
            category = NoticeCategory.SYSTEM.apiValue,
            title = "通知 $index",
            content = "内容 $index",
            read = false,
            createdAt = 1_791_888_877_000,
        )
    }
}
