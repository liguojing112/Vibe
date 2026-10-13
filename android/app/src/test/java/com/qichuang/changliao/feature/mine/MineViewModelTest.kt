package com.qichuang.changliao.feature.mine

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.Gender
import com.qichuang.changliao.core.data.model.ProfileStats
import com.qichuang.changliao.core.data.model.UserProfile
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.navigation.Routes
import io.mockk.coEvery
import io.mockk.every
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.MutableStateFlow
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
 * 我的（P04）：**男女两套菜单**是这一页最容易错的地方（`docs/01` P04 各一份），
 * 用单测钉住，不用靠肉眼看页面。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class MineViewModelTest {

    private val userRepository = mockk<UserRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()

    private val currentUser = MutableStateFlow<UserProfile?>(null)
    private val config = MutableStateFlow<AppConfig?>(null)

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { userRepository.currentUser } returns currentUser
        every { appConfigRepository.config } returns config
        coEvery { userRepository.me() } returns ApiResult.Success(male())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = MineViewModel(userRepository, appConfigRepository).also { it.enter() }

    // ---------- 男女两套菜单 ----------

    @Test
    fun `男号菜单是金币收支和会员，没有收益和实名`() {
        currentUser.value = male()

        val labels = viewModel().uiState.value.menus.map { it.label }

        assertEquals(listOf("金币收支明细", "畅撩交友会员", "每日任务", "基本资料", "隐私与设置"), labels)
    }

    @Test
    fun `女号菜单有收益等级和实名，没有会员`() {
        currentUser.value = female()
        coEvery { userRepository.me() } returns ApiResult.Success(female())

        val labels = viewModel().uiState.value.menus.map { it.label }

        assertEquals(listOf("我的收益", "我的等级与收费", "实名认证", "每日任务", "基本资料", "隐私与设置"), labels)
        assertFalse("女号不该出现会员入口", "畅撩交友会员" in labels)
    }

    @Test
    fun `通话记录只在开关打开时出现`() {
        currentUser.value = male()
        assertFalse(viewModel().uiState.value.menus.any { it.route == Routes.CALL_RECORDS })

        config.value = AppConfig(callEnabled = true)
        assertTrue(viewModel().uiState.value.menus.any { it.route == Routes.CALL_RECORDS })
    }

    /** 实名菜单右边要显示状态（docs/01 P04：去认证 / 审核中 / 已认证 / 被驳回）。 */
    @Test
    fun `实名菜单显示当前状态`() {
        currentUser.value = female(realNameStatus = 0)
        assertEquals("去认证", realNameMenu().extra)

        currentUser.value = female(realNameStatus = 1)
        assertEquals("审核中", realNameMenu().extra)

        currentUser.value = female(realNameStatus = 2)
        assertEquals("已认证", realNameMenu().extra)

        currentUser.value = female(realNameStatus = 3)
        assertEquals("被驳回", realNameMenu().extra)
    }

    // ---------- 头部与钱包卡 ----------

    @Test
    fun `男号头部是金币钱包、女号是积分收益`() {
        currentUser.value = male(coinBalance = 480)
        assertEquals("480 金币", viewModel().uiState.value.walletText)
        assertEquals("消费钱包", viewModel().uiState.value.walletDesc)
        assertEquals("去充值", viewModel().uiState.value.walletAction)
        assertEquals(Routes.COIN_WALLET, viewModel().uiState.value.walletRoute)

        currentUser.value = female(pointBalance = 6264)
        assertEquals("6264 积分", viewModel().uiState.value.walletText)
        assertEquals("我的收益", viewModel().uiState.value.walletDesc)
        assertEquals("去提现", viewModel().uiState.value.walletAction)
        assertEquals(Routes.INCOME, viewModel().uiState.value.walletRoute)
    }

    @Test
    fun `等级徽章下的字男女不同`() {
        currentUser.value = female(level = 3)
        assertEquals("魅力等级 Lv3", viewModel().uiState.value.levelText)

        currentUser.value = male(isVip = false)
        assertEquals("开通会员", viewModel().uiState.value.levelText)
        assertFalse(viewModel().uiState.value.isVip)

        currentUser.value = male(isVip = true, vipExpireAt = "2026-12-12 20:32:52")
        assertTrue(viewModel().uiState.value.isVip)
        assertTrue(
            "会员要显示剩余天数：${viewModel().uiState.value.levelText}",
            viewModel().uiState.value.levelText.startsWith("VIP 剩余 "),
        )
    }

    @Test
    fun `完善度按服务端给的显示`() {
        currentUser.value = male(completeness = 67)

        assertEquals("资料完善度 67%", viewModel().uiState.value.completenessText)
    }

    @Test
    fun `进页面会拉一次 user me`() {
        currentUser.value = male()

        val vm = viewModel()

        io.mockk.coVerify(exactly = 1) { userRepository.me() }
        assertNull(vm.uiState.value.error)
    }

    /** 拉失败也**不整页报错**：currentUser 里已经有上一次的资料了。 */
    @Test
    fun `刷新失败时页面照常显示上一次的资料`() {
        currentUser.value = male()
        coEvery { userRepository.me() } returns ApiResult.NetworkError("网络不给力")

        val state = viewModel().uiState.value

        assertNull(state.error)
        assertFalse(state.loading)
        assertEquals("男1", state.nickname)
    }

    @Test
    fun `什么资料都没有时才整页报错`() {
        coEvery { userRepository.me() } returns ApiResult.NetworkError("网络不给力")

        val state = viewModel().uiState.value

        // 没有资料 + 拉失败 → 页面进错误态（loading 必须收掉，不然一直转圈）
        assertEquals("资料加载失败，请重试", state.error)
        assertNull(state.user)
        assertFalse(state.loading)
    }

    // ---------- 工具 ----------

    private fun realNameMenu() = viewModel().uiState.value.menus.first { it.label == "实名认证" }

    private fun male(
        coinBalance: Long = 0,
        isVip: Boolean = false,
        vipExpireAt: String? = null,
        completeness: Int = 0,
    ) = UserProfile(
        id = 111,
        gender = Gender.MALE,
        nickname = "男1",
        level = 1,
        coinBalance = coinBalance,
        isVip = isVip,
        vipExpireAt = vipExpireAt,
        completeness = completeness,
        stats = ProfileStats(following = 2, friends = 1, fans = 3, visitors = 9),
    )

    private fun female(
        level: Int = 1,
        pointBalance: Long = 0,
        realNameStatus: Int = 0,
    ) = UserProfile(
        id = 113,
        gender = Gender.FEMALE,
        nickname = "女1",
        level = level,
        pointBalance = pointBalance,
        realNameStatus = realNameStatus,
        stats = ProfileStats(),
    )
}
