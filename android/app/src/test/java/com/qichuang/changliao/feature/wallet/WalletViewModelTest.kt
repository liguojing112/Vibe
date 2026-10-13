package com.qichuang.changliao.feature.wallet

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
import org.junit.Before
import org.junit.Test

/** 金币钱包（P13）：加载成功 / 出错 / 重试，以及余额卡文案跟着语音视频开关变。 */
@OptIn(ExperimentalCoroutinesApi::class)
class WalletViewModelTest {

    private val repository = mockk<WalletRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `拿到余额与单价区间`() {
        coEvery { repository.coin() } returns ApiResult.Success(
            WalletCoin(balance = 360, messagePriceMin = 5, messagePriceMax = 20, callEnabled = false),
        )

        val state = WalletViewModel(repository).uiState.value

        assertEquals(360L, state.balance)
        assertEquals(5, state.coin!!.messagePriceMin)
        assertEquals(20, state.coin!!.messagePriceMax)
        assertFalse(state.loading)
        // 开关关着时说明里不该提语音视频
        assertEquals("用于私信与礼物", state.balanceDesc)
    }

    @Test
    fun `开关打开时说明里带上语音视频`() {
        coEvery { repository.coin() } returns ApiResult.Success(WalletCoin(callEnabled = true))

        val state = WalletViewModel(repository).uiState.value

        assertEquals("用于私信、语音、视频通话与礼物", state.balanceDesc)
    }

    @Test
    fun `出错整页报错，重试再拉一次`() {
        coEvery { repository.coin() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = WalletViewModel(repository)

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)
        assertFalse(vm.uiState.value.loading)

        coEvery { repository.coin() } returns ApiResult.Success(WalletCoin(balance = 10))
        vm.onRetry()

        assertEquals(10L, vm.uiState.value.balance)
        assertNull(vm.uiState.value.error)
        coVerify(exactly = 2) { repository.coin() }
    }
}
