package com.qichuang.changliao.feature.settings

import com.qichuang.changliao.core.data.model.RelationPage
import com.qichuang.changliao.core.data.model.RelationPerson
import com.qichuang.changliao.core.data.repository.RelationRepository
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
 * 黑名单（P15）：列表、解除拉黑之后就地从列表里去掉、失败提示。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class BlockListViewModelTest {

    private val repository = mockk<RelationRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { repository.blocks(any()) } returns ApiResult.Success(
            RelationPage(list = listOf(person(9, "小满"), person(13, "晚晴")), total = 2),
        )
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = BlockListViewModel(repository).also { it.enter() }

    @Test
    fun `进页面拉到黑名单`() {
        val state = viewModel().uiState.value

        assertEquals(2, state.people.size)
        assertEquals("小满", state.people[0].nickname)
        assertFalse(state.loading)
    }

    @Test
    fun `解除拉黑之后就地把这个人去掉，不重新拉整页`() {
        coEvery { repository.unblock(9) } returns ApiResult.Success(Unit)
        val vm = viewModel()

        vm.onRemove(9)

        assertEquals(1, vm.uiState.value.people.size)
        assertEquals(13L, vm.uiState.value.people.single().id)
        assertEquals("已解除拉黑", vm.uiState.value.toast)
        assertNull(vm.uiState.value.removingId)
        coVerify(exactly = 1) { repository.blocks(any()) }
    }

    @Test
    fun `解除失败时人还在列表里并提示`() {
        coEvery { repository.unblock(9) } returns ApiResult.BizError(40001, "当前状态不允许此操作")
        val vm = viewModel()

        vm.onRemove(9)

        assertEquals(2, vm.uiState.value.people.size)
        assertEquals("当前状态不允许此操作", vm.uiState.value.toast)
    }

    @Test
    fun `网络失败也提示出来`() {
        coEvery { repository.unblock(9) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()

        vm.onRemove(9)

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `名单为空时给空状态`() {
        coEvery { repository.blocks(any()) } returns ApiResult.Success(RelationPage())

        assertTrue(viewModel().uiState.value.isEmpty)
    }

    @Test
    fun `拉不到时整页报错，重试再拉一次`() {
        coEvery { repository.blocks(any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { repository.blocks(any()) } returns ApiResult.Success(RelationPage(list = listOf(person(9, "小满"))))
        vm.onRetry()

        assertNull(vm.uiState.value.error)
        assertEquals(1, vm.uiState.value.people.size)
    }

    private fun person(id: Long, nickname: String) = RelationPerson(
        id = id,
        nickname = nickname,
        avatar = "https://cdn/$id.png",
        city = "上海",
        age = 26,
    )
}
