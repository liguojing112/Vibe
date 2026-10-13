package com.qichuang.changliao.feature.income

import com.qichuang.changliao.core.data.model.IncomeLogEntry
import com.qichuang.changliao.core.data.model.IncomeSummary
import com.qichuang.changliao.core.data.repository.IncomeRepository
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
 * 我的收益（P26）：大卡文案、今日三项、明细分页。
 *
 * 约合人民币用的是服务端给的**分**（`yuan_fen`），这里顺带守住「客户端不做除法」——
 * 1162 分就是 ¥11.62，不是 ¥11。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class IncomeViewModelTest {

    private val repository = mockk<IncomeRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { repository.summary() } returns ApiResult.Success(
            IncomeSummary(
                balance = 1162,
                frozen = 5000,
                yuanFen = 1162,
                todayPoints = 438,
                todayMessages = 4,
                todayGifts = 1,
                notice = "积分由男用户付费消息和礼物按等级比例分成获得",
            ),
        )
        coEvery { repository.logs(any()) } returns ApiResult.Success(emptyList())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = IncomeViewModel(repository).also { it.enter() }

    @Test
    fun `大卡显示可用积分和约合人民币`() {
        val state = viewModel().uiState.value

        assertEquals("1162", state.balanceText)
        assertEquals("约 ¥11.62 · 冻结中 5000 积分", state.balanceDesc)
        assertEquals("积分由男用户付费消息和礼物按等级比例分成获得", state.notice)
    }

    @Test
    fun `今日三项来自服务端`() {
        val state = viewModel().uiState.value

        assertEquals("+438", state.todayPointsText)
        assertEquals("4", state.todayMessagesText)
        assertEquals("1", state.todayGiftsText)
    }

    @Test
    fun `一页不满就没有更多了`() {
        coEvery { repository.logs(1) } returns ApiResult.Success(logs(3))

        val state = viewModel().uiState.value

        assertEquals(3, state.logs.size)
        assertFalse(state.hasMoreLogs)
    }

    @Test
    fun `满一页时还有下一页，加载更多会追加`() {
        coEvery { repository.logs(1) } returns ApiResult.Success(logs(IncomeUiState.PAGE_SIZE))
        coEvery { repository.logs(2) } returns ApiResult.Success(logs(5))

        val vm = viewModel()
        assertTrue(vm.uiState.value.hasMoreLogs)

        vm.onLoadMoreLogs()

        assertEquals(IncomeUiState.PAGE_SIZE + 5, vm.uiState.value.logs.size)
        assertEquals(2, vm.uiState.value.page)
        assertFalse("第二页不满，没有更多了", vm.uiState.value.hasMoreLogs)
    }

    @Test
    fun `没有更多时点加载更多不会发请求`() {
        coEvery { repository.logs(1) } returns ApiResult.Success(logs(2))
        val vm = viewModel()

        vm.onLoadMoreLogs()

        coVerify(exactly = 1) { repository.logs(any()) }
    }

    @Test
    fun `明细为空时给空状态`() {
        val state = viewModel().uiState.value

        assertTrue(state.logsEmpty)
    }

    /** 明细拉不到不整页报错：大卡已经出来了。 */
    @Test
    fun `明细拉不到时页面还能用`() {
        coEvery { repository.logs(any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val state = viewModel().uiState.value

        assertNull(state.error)
        assertTrue(state.logsEmpty)
    }

    @Test
    fun `拉不到收益时整页报错，重试再拉一次`() {
        coEvery { repository.summary() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { repository.summary() } returns ApiResult.Success(IncomeSummary(balance = 100))
        vm.onRetry()

        assertEquals("100", vm.uiState.value.balanceText)
        assertNull(vm.uiState.value.error)
    }

    private fun logs(count: Int) = List(count) { index ->
        IncomeLogEntry(
            id = (index + 1).toLong(),
            type = if (index % 2 == 0) "MESSAGE_INCOME" else "GIFT_INCOME",
            title = "收益 $index",
            change = 15,
            balanceAfter = 100,
            createdAt = 1_791_888_877_000,
        )
    }
}
