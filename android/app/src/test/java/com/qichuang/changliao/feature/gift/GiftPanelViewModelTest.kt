package com.qichuang.changliao.feature.gift

import com.qichuang.changliao.core.data.model.ChatMessage
import com.qichuang.changliao.core.data.model.Gift
import com.qichuang.changliao.core.data.model.GiftSendResult
import com.qichuang.changliao.core.data.model.WalletCoin
import com.qichuang.changliao.core.data.repository.GiftRepository
import com.qichuang.changliao.core.data.repository.WalletRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import com.qichuang.changliao.core.network.clJson
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
import org.junit.Assert.assertNotEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 礼物面板（P23/P31）：列表与默认选中、按钮文案、赠送成功、余额不足弹 P30、
 * 以及**订单号在失败重试时复用、换礼物时重新生成**（这关系到会不会重复扣钱）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class GiftPanelViewModelTest {

    private val giftRepository = mockk<GiftRepository>()
    private val walletRepository = mockk<WalletRepository>()

    private val gifts = listOf(
        Gift(id = 1, name = "一朵玫瑰", icon = "🌹", priceCoins = 10),
        Gift(id = 4, name = "浪漫花束", icon = "💐", priceCoins = 120),
    )

    private val peerId = 113L

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { giftRepository.list() } returns ApiResult.Success(gifts)
        coEvery { walletRepository.coin() } returns ApiResult.Success(WalletCoin(balance = 360))
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = GiftPanelViewModel(giftRepository, walletRepository).also { it.enter(peerId) }

    @Test
    fun `进面板拿到礼物并默认选中第一个`() {
        val state = viewModel().uiState.value

        assertEquals(2, state.gifts.size)
        assertEquals(1L, state.selectedId)
        assertEquals(360L, state.balance)
        assertEquals("可用余额 360 金币", state.balanceText)
        assertFalse(state.loading)
    }

    @Test
    fun `选中的礼物决定底部按钮文案`() {
        val vm = viewModel()
        assertEquals("赠送一朵玫瑰 · 10 金币", vm.uiState.value.buttonText)

        vm.onSelect(gifts[1])

        assertEquals("赠送浪漫花束 · 120 金币", vm.uiState.value.buttonText)
    }

    @Test
    fun `同一个对象重复进面板不重复拉数据`() {
        val vm = viewModel()

        vm.enter(peerId)

        coVerify(exactly = 1) { giftRepository.list() }
    }

    @Test
    fun `赠送成功按服务端返回刷新余额`() {
        val vm = viewModel()
        coEvery { giftRepository.send(any(), any(), any()) } returns ApiResult.Success(
            GiftSendResult(message = message(), balance = 240),
        )

        vm.onSend()

        assertEquals(240L, vm.uiState.value.balance)
        assertEquals(1L, vm.uiState.value.sentCount)
        assertEquals("礼物已送出", vm.uiState.value.toast)
        assertFalse(vm.uiState.value.sending)
    }

    @Test
    fun `余额不足弹 P30，余额不变`() {
        val vm = viewModel()
        vm.onSelect(gifts[1])
        coEvery { giftRepository.send(any(), any(), any()) } returns ApiResult.BizError(
            code = GiftPanelViewModel.CODE_COIN_NOT_ENOUGH,
            message = "金币余额不足",
            data = clJson.parseToJsonElement("""{"balance":30,"needed":120}"""),
        )

        vm.onSend()

        val info = vm.uiState.value.coinNotEnough
        assertEquals(30L, info!!.balance)
        assertEquals(120L, info.needed)
        assertEquals(90L, info.short)
        assertEquals("余额没变（这次没扣成）", 360L, vm.uiState.value.balance)
        assertNull(vm.uiState.value.toast)

        vm.onDismissCoinNotEnough()
        assertNull(vm.uiState.value.coinNotEnough)
    }

    /** 网络失败后再点一次**用的是同一个订单号**：服务端靠它幂等，不会重复扣钱。 */
    @Test
    fun `发送失败重试复用同一个订单号`() {
        val vm = viewModel()
        val orderIds = mutableListOf<String>()
        coEvery { giftRepository.send(any(), any(), any()) } answers {
            orderIds += thirdArg<String>()
            ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        }

        vm.onSend()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
        vm.onSend()

        assertEquals(2, orderIds.size)
        assertEquals("两次失败重试要用同一个订单号", orderIds[0], orderIds[1])

        // 第三次终于成功：用的还是同一个订单号（这一次才是「重试成功」）
        coEvery { giftRepository.send(any(), any(), any()) } answers {
            orderIds += thirdArg<String>()
            ApiResult.Success(GiftSendResult(message = message(), balance = 100))
        }
        vm.onSend()
        assertEquals("成功那一次还是同一单", orderIds[1], orderIds[2])

        // 再送一次就是新的一单了（成功之后订单号已经清掉）
        vm.onSend()
        assertEquals(4, orderIds.size)
        assertNotEquals("送成功之后要重新生成订单号", orderIds[2], orderIds[3])
    }

    /** 换礼物就是另一单，订单号必须重新生成。 */
    @Test
    fun `换礼物之后订单号重新生成`() {
        val vm = viewModel()
        val orderIds = mutableListOf<String>()
        coEvery { giftRepository.send(any(), any(), any()) } answers {
            orderIds += thirdArg<String>()
            ApiResult.Success(GiftSendResult(message = message(), balance = 100))
        }

        vm.onSend()
        vm.onSelect(gifts[1])
        vm.onSend()

        assertEquals(2, orderIds.size)
        assertNotEquals(orderIds[0], orderIds[1])
    }

    @Test
    fun `礼物列表为空时给空状态`() {
        coEvery { giftRepository.list() } returns ApiResult.Success(emptyList())

        val state = viewModel().uiState.value

        assertTrue(state.isEmpty)
        assertNull(state.selectedId)
        assertFalse(state.canSend)
        assertEquals("请选择礼物", state.buttonText)
    }

    @Test
    fun `列表拉不到时整页报错，重试再拉一次`() {
        coEvery { giftRepository.list() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { giftRepository.list() } returns ApiResult.Success(gifts)
        vm.onRetry()

        assertEquals(2, vm.uiState.value.gifts.size)
        assertNull(vm.uiState.value.error)
    }

    private fun message() = ChatMessage(
        localId = 1,
        clientMsgId = "gift-1",
        peerId = peerId,
        outgoing = true,
        type = ChatMessage.TYPE_GIFT,
        content = "一朵玫瑰",
        createdAt = 1_791_888_877_000,
    )
}
