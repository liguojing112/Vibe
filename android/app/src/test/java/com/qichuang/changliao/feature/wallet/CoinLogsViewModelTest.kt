package com.qichuang.changliao.feature.wallet

import com.qichuang.changliao.core.data.model.CoinLogEntry
import com.qichuang.changliao.core.data.model.CoinLogPage
import com.qichuang.changliao.core.data.model.CoinLogTab
import com.qichuang.changliao.core.data.model.WalletCoin
import com.qichuang.changliao.core.data.repository.WalletRepository
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

/** 金币收支明细（P24）：三个 Tab、分页、分页失败只提示底部、顶部当前余额。 */
@OptIn(ExperimentalCoroutinesApi::class)
class CoinLogsViewModelTest {

    private val repository = mockk<WalletRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { repository.coin() } returns ApiResult.Success(WalletCoin(balance = 240))
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun page(
        entries: List<CoinLogEntry> = listOf(entry(id = 1, title = "私信 · 小满", change = -5)),
        hasMore: Boolean = false,
        page: Int = 1,
    ) = ApiResult.Success(CoinLogPage(list = entries, total = entries.size, page = page, hasMore = hasMore))

    @Test
    fun `首屏拿到明细和当前余额`() {
        coEvery { repository.coinLogs(CoinLogTab.ALL, 1) } returns page()

        val state = CoinLogsViewModel(repository).uiState.value

        assertEquals(1, state.entries.size)
        assertEquals("私信 · 小满", state.entries.single().title)
        assertEquals(-5L, state.entries.single().change)
        assertFalse(state.entries.single().isIncome)
        assertEquals(240L, state.balance)
        assertFalse(state.loading)
    }

    @Test
    fun `切 Tab 会清空旧列表重新拉`() {
        coEvery { repository.coinLogs(CoinLogTab.ALL, 1) } returns page(
            entries = listOf(entry(id = 1, title = "私信 · 小满", change = -5)),
        )
        coEvery { repository.coinLogs(CoinLogTab.RECHARGE, 1) } returns page(
            entries = listOf(entry(id = 2, title = "客服充值", change = 100)),
        )
        val vm = CoinLogsViewModel(repository)

        vm.onTabSelected(CoinLogTab.RECHARGE)

        assertEquals(CoinLogTab.RECHARGE, vm.uiState.value.tab)
        assertEquals(listOf("客服充值"), vm.uiState.value.entries.map { it.title })

        // 点同一个 Tab 不重复请求
        vm.onTabSelected(CoinLogTab.RECHARGE)
        coVerify(exactly = 1) { repository.coinLogs(CoinLogTab.RECHARGE, 1) }
    }

    @Test
    fun `上拉加载下一页并追加`() {
        coEvery { repository.coinLogs(CoinLogTab.ALL, 1) } returns page(
            entries = listOf(entry(id = 2, title = "第二页之前")),
            hasMore = true,
        )
        coEvery { repository.coinLogs(CoinLogTab.ALL, 2) } returns page(
            entries = listOf(entry(id = 1, title = "更早的一条")),
            page = 2,
        )
        val vm = CoinLogsViewModel(repository)

        assertTrue(vm.uiState.value.hasMore)
        vm.onLoadMore()

        assertEquals(listOf("第二页之前", "更早的一条"), vm.uiState.value.entries.map { it.title })
        assertFalse(vm.uiState.value.hasMore)
        assertFalse(vm.uiState.value.loadingMore)
    }

    @Test
    fun `没有下一页时上拉不发请求`() {
        coEvery { repository.coinLogs(CoinLogTab.ALL, 1) } returns page(hasMore = false)
        val vm = CoinLogsViewModel(repository)

        vm.onLoadMore()

        coVerify(exactly = 0) { repository.coinLogs(CoinLogTab.ALL, 2) }
    }

    /** 分页失败只在列表底部提示，不覆盖整页（docs/03 第 6 节第 3 条）。 */
    @Test
    fun `分页失败不覆盖整页，只在底部提示`() {
        coEvery { repository.coinLogs(CoinLogTab.ALL, 1) } returns page(hasMore = true)
        coEvery { repository.coinLogs(CoinLogTab.ALL, 2) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = CoinLogsViewModel(repository)

        vm.onLoadMore()

        assertNull(vm.uiState.value.error)
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.loadMoreError)
        assertEquals(1, vm.uiState.value.entries.size)
    }

    @Test
    fun `空列表按 Tab 给不同文案`() {
        coEvery { repository.coinLogs(CoinLogTab.SPEND, 1) } returns page(entries = emptyList())
        val vm = CoinLogsViewModel(repository)

        vm.onTabSelected(CoinLogTab.SPEND)

        assertTrue(vm.uiState.value.isEmpty)
        assertEquals("还没有消费记录", vm.uiState.value.emptyTitle)
    }

    @Test
    fun `首屏失败整页报错`() {
        coEvery { repository.coinLogs(CoinLogTab.ALL, 1) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val state = CoinLogsViewModel(repository).uiState.value

        assertEquals(NETWORK_ERROR_MESSAGE, state.error)
        assertFalse(state.loading)
    }

    private fun entry(
        id: Long,
        title: String,
        change: Long = -5,
        balanceAfter: Long = 100,
    ) = CoinLogEntry(
        id = id,
        type = if (change > 0) "RECHARGE" else "MESSAGE",
        title = title,
        change = change,
        balanceAfter = balanceAfter,
        createdAt = 1_791_888_877_000,
    )
}
