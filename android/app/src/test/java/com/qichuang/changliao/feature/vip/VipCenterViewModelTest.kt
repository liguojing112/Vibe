package com.qichuang.changliao.feature.vip

import com.qichuang.changliao.core.data.model.VipBenefit
import com.qichuang.changliao.core.data.model.VipBuyResult
import com.qichuang.changliao.core.data.model.VipInfo
import com.qichuang.changliao.core.data.model.VipPlan
import com.qichuang.changliao.core.data.repository.VipRepository
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
 * 会员中心（P12）：套餐与默认选中、按钮文案、开通成功、余额不足弹 P30、
 * 以及**订单号在失败重试时复用、换套餐时重新生成**（这关系到会不会重复扣钱）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class VipCenterViewModelTest {

    private val vipRepository = mockk<VipRepository>()

    private val plans = listOf(
        VipPlan(id = 1, name = "月卡", days = 30, priceCoins = 390, priceYuan = 39),
        VipPlan(id = 3, name = "年卡", days = 365, priceCoins = 2990, priceYuan = 299),
    )

    private val benefits = listOf(
        VipBenefit(icon = "👀", title = "查看访客", desc = "谁来看过你，一个不漏"),
        VipBenefit(icon = "💬", title = "每日额外免费私信 10 条", desc = "每天零点重置，所有对象合计"),
    )

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { vipRepository.info() } returns ApiResult.Success(
            VipInfo(
                isVip = false,
                plans = plans,
                benefits = benefits,
                balance = 500,
                notice = "会员不抵扣礼物费用；每日额外免费私信用完后按单价扣费",
            ),
        )
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = VipCenterViewModel(vipRepository).also { it.enter() }

    @Test
    fun `进页面拿到套餐与权益并默认选中第一个`() {
        val state = viewModel().uiState.value

        assertEquals(2, state.plans.size)
        assertEquals(1L, state.selectedId)
        assertEquals(2, state.benefits.size)
        assertEquals(500L, state.balance)
        assertEquals("¥39", state.priceYuan)
        assertEquals("30 天", state.daysText)
        assertEquals("会员不抵扣礼物费用；每日额外免费私信用完后按单价扣费", state.notice)
        assertFalse(state.loading)
    }

    @Test
    fun `选中的套餐决定底部按钮与横幅价格`() {
        val vm = viewModel()
        assertEquals("立即开通 月卡 · 390 金币", vm.uiState.value.buttonText)

        vm.onSelect(plans[1])

        assertEquals("立即开通 年卡 · 2990 金币", vm.uiState.value.buttonText)
        assertEquals("¥299", vm.uiState.value.priceYuan)
        assertEquals("365 天", vm.uiState.value.daysText)
    }

    /** 已经是会员时按钮写「续费」、横幅副标题换成到期时间（`docs/01` P12）。 */
    @Test
    fun `会员看到的按钮是续费，横幅显示到期时间`() {
        coEvery { vipRepository.info() } returns ApiResult.Success(
            VipInfo(isVip = true, expireAt = "2026-12-12 20:32:52", plans = plans, balance = 840),
        )

        val state = viewModel().uiState.value

        assertEquals("续费 月卡 · 390 金币", state.buttonText)
        assertEquals("有效期至 2026-12-12", state.expireText)
        assertEquals("有效期至 2026-12-12", state.heroSubtitle)
    }

    @Test
    fun `不是会员时横幅放宣传语`() {
        assertEquals("更高曝光 · 更多匹配机会 · 专属互动权益", viewModel().uiState.value.heroSubtitle)
    }

    @Test
    fun `重复进页面不重复拉数据`() {
        val vm = viewModel()

        vm.enter()

        coVerify(exactly = 1) { vipRepository.info() }
    }

    @Test
    fun `开通成功按服务端返回刷新到期时间与余额`() {
        val vm = viewModel()
        coEvery { vipRepository.buy(any(), any()) } returns ApiResult.Success(
            VipBuyResult(expireAt = "2026-11-12 20:32:52", days = 30, priceCoins = 390, balance = 110),
        )

        vm.onBuy()

        val state = vm.uiState.value
        assertEquals(110L, state.balance)
        assertTrue(state.isVip)
        assertEquals("2026-11-12 20:32:52", state.expireAt)
        assertEquals("有效期至 2026-11-12", state.expireText)
        assertEquals("续费 月卡 · 390 金币", state.buttonText)
        assertEquals(1L, state.boughtCount)
        assertEquals("开通成功", state.toast)
        assertFalse(state.buying)
    }

    @Test
    fun `余额不足弹 P30，余额不变`() {
        val vm = viewModel()
        vm.onSelect(plans[1])
        coEvery { vipRepository.buy(any(), any()) } returns ApiResult.BizError(
            code = VipCenterViewModel.CODE_COIN_NOT_ENOUGH,
            message = "金币余额不足",
            data = clJson.parseToJsonElement("""{"balance":120,"needed":2990}"""),
        )

        vm.onBuy()

        val info = vm.uiState.value.coinNotEnough
        assertEquals(120L, info!!.balance)
        assertEquals(2990L, info.needed)
        assertEquals(2870L, info.short)
        assertEquals("余额没变（这次没扣成）", 500L, vm.uiState.value.balance)
        assertFalse("没买成就不能变成会员", vm.uiState.value.isVip)
        assertNull(vm.uiState.value.toast)

        vm.onDismissCoinNotEnough()
        assertNull(vm.uiState.value.coinNotEnough)
    }

    /** 网络失败后再点一次**用的是同一个订单号**：服务端靠它幂等，不会重复扣钱。 */
    @Test
    fun `购买失败重试复用同一个订单号`() {
        val vm = viewModel()
        val orderIds = mutableListOf<String>()
        coEvery { vipRepository.buy(any(), any()) } answers {
            orderIds += secondArg<String>()
            ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        }

        vm.onBuy()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
        vm.onBuy()

        assertEquals(2, orderIds.size)
        assertEquals("两次失败重试要用同一个订单号", orderIds[0], orderIds[1])

        // 第三次终于成功：用的还是同一个订单号（这一次才是「重试成功」）
        coEvery { vipRepository.buy(any(), any()) } answers {
            orderIds += secondArg<String>()
            ApiResult.Success(VipBuyResult(expireAt = "2026-11-12 20:32:52", days = 30, balance = 110))
        }
        vm.onBuy()
        assertEquals("成功那一次还是同一单", orderIds[1], orderIds[2])

        // 再买一次就是新的一单了（成功之后订单号已经清掉）
        vm.onBuy()
        assertEquals(4, orderIds.size)
        assertNotEquals("买成功之后要重新生成订单号", orderIds[2], orderIds[3])
    }

    /** 换套餐就是另一单，订单号必须重新生成。 */
    @Test
    fun `换套餐之后订单号重新生成`() {
        val vm = viewModel()
        val orderIds = mutableListOf<String>()
        coEvery { vipRepository.buy(any(), any()) } answers {
            orderIds += secondArg<String>()
            ApiResult.Success(VipBuyResult(expireAt = "2026-11-12 20:32:52", days = 30, balance = 110))
        }

        vm.onBuy()
        vm.onSelect(plans[1])
        vm.onBuy()

        assertEquals(2, orderIds.size)
        assertNotEquals(orderIds[0], orderIds[1])
    }

    @Test
    fun `套餐列表为空时给空状态`() {
        coEvery { vipRepository.info() } returns ApiResult.Success(VipInfo(plans = emptyList()))

        val state = viewModel().uiState.value

        assertTrue(state.isEmpty)
        assertNull(state.selectedId)
        assertFalse(state.canBuy)
        assertEquals("请选择套餐", state.buttonText)
        assertEquals("", state.priceYuan)
    }

    @Test
    fun `列表拉不到时整页报错，重试再拉一次`() {
        coEvery { vipRepository.info() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { vipRepository.info() } returns ApiResult.Success(VipInfo(plans = plans, balance = 500))
        vm.onRetry()

        assertEquals(2, vm.uiState.value.plans.size)
        assertNull(vm.uiState.value.error)
    }
}
