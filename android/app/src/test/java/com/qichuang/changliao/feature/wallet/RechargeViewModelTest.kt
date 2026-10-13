package com.qichuang.changliao.feature.wallet

import com.qichuang.changliao.core.data.model.RechargeInfo
import com.qichuang.changliao.core.data.model.RechargePack
import com.qichuang.changliao.core.data.model.RechargeService
import com.qichuang.changliao.core.data.repository.WalletRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
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

/** 充值页（P22）：档位默认选中、切换档位、客服信息、没档位就别摆空网格。 */
@OptIn(ExperimentalCoroutinesApi::class)
class RechargeViewModelTest {

    private val repository = mockk<WalletRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun info(
        packs: List<RechargePack> = listOf(RechargePack(60, 6), RechargePack(300, 30)),
        wechat: String = "changliao_kf",
    ) = RechargeInfo(
        balance = 360,
        packs = packs,
        service = RechargeService(wechat = wechat),
        complianceDisclosure = "本平台为付费聊天服务。",
        notice = "选好档位后联系客服充值，客服确认后金币到账",
    )

    @Test
    fun `默认选中第一个档位，切换后本次到账跟着变`() {
        coEvery { repository.rechargeInfo() } returns ApiResult.Success(info())
        val vm = RechargeViewModel(repository)

        assertEquals(60, vm.uiState.value.selectedPack?.coins)
        assertEquals("60 金币", vm.uiState.value.selectedCoinsText)

        vm.onSelectPack(RechargePack(300, 30))

        assertEquals("300 金币", vm.uiState.value.selectedCoinsText)
        assertEquals(360L, vm.uiState.value.balance)
    }

    @Test
    fun `后台没配档位时列表为空、本次到账显示占位`() {
        coEvery { repository.rechargeInfo() } returns ApiResult.Success(info(packs = emptyList()))

        val state = RechargeViewModel(repository).uiState.value

        assertTrue(state.info!!.packs.isEmpty())
        assertNull(state.selectedPack)
        assertEquals("请选择档位", state.selectedCoinsText)
    }

    @Test
    fun `没配客服微信时不给复制按钮`() {
        coEvery { repository.rechargeInfo() } returns ApiResult.Success(info(wechat = ""))

        val state = RechargeViewModel(repository).uiState.value

        assertFalse(state.canCopyWechat)
    }

    @Test
    fun `复制之后给个提示`() {
        coEvery { repository.rechargeInfo() } returns ApiResult.Success(info())
        val vm = RechargeViewModel(repository)

        vm.onWechatCopied()
        assertEquals("客服微信已复制", vm.uiState.value.toast)

        vm.onToastShown()
        assertNull(vm.uiState.value.toast)
    }

    @Test
    fun `出错整页报错`() {
        coEvery { repository.rechargeInfo() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        val state = RechargeViewModel(repository).uiState.value

        assertEquals(NETWORK_ERROR_MESSAGE, state.error)
        assertFalse(state.loading)
    }
}
