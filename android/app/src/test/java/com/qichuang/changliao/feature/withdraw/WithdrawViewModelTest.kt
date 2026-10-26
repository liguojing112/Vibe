package com.qichuang.changliao.feature.withdraw

import com.qichuang.changliao.core.data.model.WithdrawAccount
import com.qichuang.changliao.core.data.model.WithdrawAccountType
import com.qichuang.changliao.core.data.model.WithdrawApplyResult
import com.qichuang.changliao.core.data.model.WithdrawInfo
import com.qichuang.changliao.core.data.model.WithdrawRecord
import com.qichuang.changliao.core.data.model.WithdrawTier
import com.qichuang.changliao.core.data.repository.WithdrawRepository
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
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 收益提现（P28）：档位默认选中、未实名拦提交、收款信息回填、提交成功重拉、
 * 业务错误（20002 积分不足 / 40001）原样提示。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class WithdrawViewModelTest {

    private val repository = mockk<WithdrawRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { repository.info() } returns ApiResult.Success(info())
        coEvery { repository.records(any()) } returns ApiResult.Success(emptyList())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = WithdrawViewModel(repository).also { it.enter() }

    @Test
    fun `进页面拿到可提现金额与档位，默认选中第一个够的`() {
        val state = viewModel().uiState.value

        assertEquals("¥120.00", state.amountText)
        assertEquals("12000 积分", state.pointsText)
        // 50 和 100 都够，默认选第一个（50）
        assertEquals(50, state.selectedYuan)
    }

    @Test
    fun `积分只够最低档时默认选最低档`() {
        coEvery { repository.info() } returns ApiResult.Success(
            info(
                points = 5000,
                tiers = listOf(
                    tier(50, 5000, enabled = true),
                    tier(100, 10000, enabled = false),
                ),
            ),
        )

        val state = viewModel().uiState.value

        assertEquals(50, state.selectedYuan)
        assertFalse("还没填收款账号", state.canSubmit)
    }

    @Test
    fun `填了收款账号才能提交`() {
        val vm = viewModel()
        assertFalse(vm.uiState.value.canSubmit)

        vm.onAccountNoChange("late@example.com")
        assertTrue(vm.uiState.value.canSubmit)

        // 银行卡必须填开户行
        vm.onAccountTypeChange(WithdrawAccountType.BANK)
        assertFalse(vm.uiState.value.canSubmit)
        vm.onBankNameChange("招商银行")
        assertTrue(vm.uiState.value.canSubmit)
    }

    @Test
    fun `未实名时按钮是去实名，且不能提交`() {
        coEvery { repository.info() } returns ApiResult.Success(info(realNamePassed = false, realName = ""))

        val state = viewModel().uiState.value

        assertTrue(state.realNameMissing)
        assertEquals("先完成实名认证", state.buttonText)
        assertFalse(state.canSubmit)
    }

    @Test
    fun `上次填的收款信息自动带出来`() {
        coEvery { repository.info() } returns ApiResult.Success(
            info(account = WithdrawAccount(WithdrawAccountType.WECHAT, "wx-001", null)),
        )

        val state = viewModel().uiState.value

        assertEquals(WithdrawAccountType.WECHAT, state.accountType)
        assertEquals("wx-001", state.accountNo)
    }

    @Test
    fun `收款人姓名用服务端给的实名姓名`() {
        assertEquals("晚晴", viewModel().uiState.value.accountNameText)
    }

    @Test
    fun `提交成功后提示并重新拉一遍`() {
        val vm = viewModel()
        vm.onAccountNoChange("late@example.com")
        coEvery { repository.apply(any(), any(), any(), any()) } returns ApiResult.Success(
            WithdrawApplyResult(orderNo = "W1", amountFen = 5000, points = 5000, statusText = "待审核"),
        )

        vm.onSubmit()

        assertEquals("已提交，等待审核", vm.uiState.value.toast)
        assertFalse(vm.uiState.value.submitting)
        // 进页面一次 + 提交成功后一次
        coVerify(exactly = 2) { repository.info() }
    }

    @Test
    fun `积分不足时把 20002 的话原样提示`() {
        val vm = viewModel()
        vm.onAccountNoChange("late@example.com")
        coEvery { repository.apply(any(), any(), any(), any()) } returns ApiResult.BizError(
            code = 20002,
            message = "积分不足",
            data = clJson.parseToJsonElement("""{"balance":4000,"needed":5000}"""),
        )

        vm.onSubmit()

        assertEquals("积分不足", vm.uiState.value.toast)
        coVerify(exactly = 1) { repository.info() }
    }

    @Test
    fun `次数用完时把 40001 的话原样提示`() {
        val vm = viewModel()
        vm.onAccountNoChange("late@example.com")
        coEvery { repository.apply(any(), any(), any(), any()) } returns ApiResult.BizError(
            code = 40001,
            message = "今天已经申请过了，请明天再来",
        )

        vm.onSubmit()

        assertEquals("今天已经申请过了，请明天再来", vm.uiState.value.toast)
    }

    @Test
    fun `网络失败提示网络错误`() {
        val vm = viewModel()
        vm.onAccountNoChange("late@example.com")
        coEvery { repository.apply(any(), any(), any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        vm.onSubmit()

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `提交时传的是选中的档位和收款信息`() {
        val vm = viewModel()
        vm.onSelectTier(200)
        vm.onAccountTypeChange(WithdrawAccountType.ALIPAY)
        vm.onAccountNoChange("late@example.com")
        coEvery { repository.apply(any(), any(), any(), any()) } returns ApiResult.Success(WithdrawApplyResult())

        vm.onSubmit()

        coVerify(exactly = 1) {
            repository.apply(200, WithdrawAccountType.ALIPAY, "late@example.com", "")
        }
    }

    @Test
    fun `规则说明里有次数和手续费`() {
        val state = viewModel().uiState.value

        assertEquals("今日还可申请 1 次 · 免手续费 · 提交后 1~3 个工作日内审核打款", state.ruleText)
    }

    @Test
    fun `不限次时说明写不限次数`() {
        coEvery { repository.info() } returns ApiResult.Success(info(remainingToday = null, dailyLimit = 0))

        assertEquals("每日不限次数 · 免手续费 · 提交后 1~3 个工作日内审核打款", viewModel().uiState.value.ruleText)
    }

    @Test
    fun `记录满一页时能加载更多`() {
        coEvery { repository.records(1) } returns ApiResult.Success(records(WithdrawUiState.PAGE_SIZE))
        coEvery { repository.records(2) } returns ApiResult.Success(records(2))

        val vm = viewModel()
        assertTrue(vm.uiState.value.hasMoreRecords)

        vm.onLoadMoreRecords()

        assertEquals(WithdrawUiState.PAGE_SIZE + 2, vm.uiState.value.records.size)
        assertFalse(vm.uiState.value.hasMoreRecords)
    }

    @Test
    fun `拉不到时整页报错，重试再拉一次`() {
        coEvery { repository.info() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { repository.info() } returns ApiResult.Success(info())
        vm.onRetry()

        assertNull(vm.uiState.value.error)
        assertEquals("¥120.00", vm.uiState.value.amountText)
    }

    /** 后台改了提现档位，用户从别的页面回到提现页时应该马上看到新档位。 */
    @Test
    fun `回到提现页时静默刷新档位，原档位没了就选新的第一个`() {
        val vm = viewModel()
        assertEquals(50, vm.uiState.value.selectedYuan)

        coEvery { repository.info() } returns ApiResult.Success(
            info(tiers = listOf(tier(20, 2000, enabled = true), tier(100, 10000, enabled = true))),
        )
        vm.onResume()

        assertEquals(listOf(20, 100), vm.uiState.value.info?.tiers?.map { it.yuan })
        assertEquals(20, vm.uiState.value.selectedYuan)
    }

    /** 静默刷新不能把用户正在填的收款账号覆盖掉。 */
    @Test
    fun `静默刷新保留正在编辑的收款信息`() {
        val vm = viewModel()
        vm.onAccountNoChange("draft@example.com")

        coEvery { repository.info() } returns ApiResult.Success(
            info(account = WithdrawAccount(WithdrawAccountType.ALIPAY, "old@example.com", null)),
        )
        vm.onResume()

        assertEquals("draft@example.com", vm.uiState.value.accountNo)
    }

    /** 第一次 onResume 就是进页面：只请求一次，不要和 enter 重复拉。 */
    @Test
    fun `首次 onResume 等价于进页面`() {
        val vm = WithdrawViewModel(repository)

        vm.onResume()

        assertTrue(vm.uiState.value.info != null)
        coVerify(exactly = 1) { repository.info() }
    }

    private fun info(
        points: Long = 12000,
        realNamePassed: Boolean = true,
        realName: String = "晚晴",
        account: WithdrawAccount? = null,
        remainingToday: Int? = 1,
        dailyLimit: Int = 1,
        tiers: List<WithdrawTier> = listOf(
            tier(50, 5000, enabled = true),
            tier(100, 10000, enabled = true),
            tier(200, 20000, enabled = false),
            tier(500, 50000, enabled = false),
        ),
    ) = WithdrawInfo(
        amountYuan = points / 100,
        points = points,
        frozenPoints = 0,
        tiers = tiers,
        feeRate = 0,
        notice = "提交后 1~3 个工作日内审核打款",
        realNamePassed = realNamePassed,
        realName = realName,
        needRealName = true,
        account = account,
        dailyLimit = dailyLimit,
        todayCount = if (remainingToday == null) 0 else dailyLimit - remainingToday,
        remainingToday = remainingToday,
    )

    private fun tier(yuan: Int, points: Long, enabled: Boolean) = WithdrawTier(
        yuan = yuan,
        points = points,
        feeFen = 0,
        actualFen = yuan * 100L,
        enabled = enabled,
    )

    private fun records(count: Int) = List(count) { index ->
        WithdrawRecord(
            id = (index + 1).toLong(),
            orderNo = "W$index",
            amountFen = 5000,
            actualFen = 5000,
            status = WithdrawRecord.STATUS_PENDING,
            statusText = "待审核",
            createdAt = 1_791_888_877_000,
        )
    }
}
