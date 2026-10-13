package com.qichuang.changliao.feature.profile

import com.qichuang.changliao.core.data.model.AppConfig
import com.qichuang.changliao.core.data.model.AppTag
import com.qichuang.changliao.core.data.model.RelationState
import com.qichuang.changliao.core.data.model.UserDetail
import com.qichuang.changliao.core.data.model.UserDetailPrice
import com.qichuang.changliao.core.data.model.UserPhoto
import com.qichuang.changliao.core.data.repository.AppConfigRepository
import com.qichuang.changliao.core.data.model.ReportResult
import com.qichuang.changliao.core.data.repository.NoticeRepository
import com.qichuang.changliao.core.data.repository.RelationRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
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
 * 个人详情页（docs/01 P05）：资料与标签、关注按钮文案、关注/拉黑/举报、价格面板。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class UserDetailViewModelTest {

    private val userRepository = mockk<UserRepository>()
    private val relationRepository = mockk<RelationRepository>()
    private val noticeRepository = mockk<NoticeRepository>()
    private val appConfigRepository = mockk<AppConfigRepository>()

    private val config = MutableStateFlow<AppConfig?>(
        AppConfig(tags = listOf(AppTag(1, "旅行"), AppTag(2, "音乐"))),
    )

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        every { appConfigRepository.config } returns config
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun detail(
        following: Boolean = false,
        mutual: Boolean = false,
        price: UserDetailPrice? = null,
    ) = UserDetail(
        id = 9,
        nickname = "小满",
        avatar = "https://cdn/a.png",
        age = 26,
        city = "上海",
        job = "设计师",
        education = "本科",
        bio = "喜欢逛展",
        tags = listOf(1L, 2L, 99L),
        verified = true,
        online = true,
        isNew = false,
        isFollowing = following,
        isMutual = mutual,
        price = price,
    )

    private fun priceOf(message: Long = 5) = UserDetailPrice(messagePrice = message)

    private fun viewModel(current: UserDetail = detail()): UserDetailViewModel {
        coEvery { userRepository.detail(9) } returns ApiResult.Success(current)

        return UserDetailViewModel(userRepository, relationRepository, noticeRepository, appConfigRepository)
    }

    private fun loaded(current: UserDetail = detail()): UserDetailViewModel {
        val viewModel = viewModel(current)
        viewModel.load(9)

        return viewModel
    }

    // ---------- 资料 ----------

    @Test
    fun `加载资料并把标签 id 换成名字`() {
        val state = loaded().uiState.value

        assertFalse(state.loading)
        assertEquals("小满", state.detail?.nickname)
        // 99 不在标签库里，直接丢掉
        assertEquals(listOf("旅行", "音乐"), state.tagNames)
    }

    @Test
    fun `加载失败给出错误`() {
        coEvery { userRepository.detail(9) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val viewModel = UserDetailViewModel(userRepository, relationRepository, noticeRepository, appConfigRepository)

        viewModel.load(9)

        assertEquals(NETWORK_ERROR_MESSAGE, viewModel.uiState.value.error)
        assertNull(viewModel.uiState.value.detail)
    }

    // ---------- 价格 ----------

    @Test
    fun `男看女带价格时按钮写单价`() {
        val state = loaded(detail(price = priceOf(5))).uiState.value

        assertTrue(state.showPricePanel)
        assertEquals("私信聊天 5 币/条", state.chatLabel)
    }

    @Test
    fun `没有价格时不显示价格面板`() {
        val state = loaded(detail(price = null)).uiState.value

        assertFalse(state.showPricePanel)
        assertEquals("私信聊天", state.chatLabel)
    }

    // ---------- 关注 ----------

    @Test
    fun `关注按钮文案三态`() {
        assertEquals("关注", loaded(detail()).uiState.value.followLabel)
        assertEquals("已关注", loaded(detail(following = true)).uiState.value.followLabel)
        assertEquals("互相关注", loaded(detail(following = true, mutual = true)).uiState.value.followLabel)
    }

    @Test
    fun `没关注时点关注调 follow`() {
        val viewModel = loaded()
        coEvery { relationRepository.follow(9) } returns ApiResult.Success(
            RelationState(isFollowing = true),
        )

        viewModel.onToggleFollow()

        coVerify { relationRepository.follow(9) }
        coVerify(exactly = 0) { relationRepository.unfollow(any()) }
        assertTrue(viewModel.uiState.value.isFollowing)
        assertEquals("已关注", viewModel.uiState.value.followLabel)
    }

    @Test
    fun `已关注时点一下是取关`() {
        val viewModel = loaded(detail(following = true))
        coEvery { relationRepository.unfollow(9) } returns ApiResult.Success(RelationState.NONE)

        viewModel.onToggleFollow()

        coVerify { relationRepository.unfollow(9) }
        assertFalse(viewModel.uiState.value.isFollowing)
        assertEquals("关注", viewModel.uiState.value.followLabel)
    }

    @Test
    fun `回关后立刻显示互相关注`() {
        val viewModel = loaded()
        coEvery { relationRepository.follow(9) } returns ApiResult.Success(
            RelationState(isFollowing = true, isMutual = true),
        )

        viewModel.onToggleFollow()

        assertEquals("互相关注", viewModel.uiState.value.followLabel)
    }

    @Test
    fun `关注后不重新拉整页`() {
        val viewModel = loaded()
        coEvery { relationRepository.follow(9) } returns ApiResult.Success(RelationState(isFollowing = true))

        viewModel.onToggleFollow()

        // /user/{id} 每调一次就记一条访客，所以不能为了刷新状态再拉一遍
        coVerify(exactly = 1) { userRepository.detail(9) }
    }

    @Test
    fun `关注失败把服务端文案弹出来`() {
        val viewModel = loaded()
        coEvery { relationRepository.follow(9) } returns ApiResult.BizError(30001, "对方不存在或已注销")

        viewModel.onToggleFollow()

        assertEquals("对方不存在或已注销", viewModel.uiState.value.toast)
        assertFalse(viewModel.uiState.value.isFollowing)
    }

    // ---------- 拉黑 ----------

    @Test
    fun `确认拉黑后标记要回上一页`() {
        val viewModel = loaded()
        coEvery { relationRepository.block(9) } returns ApiResult.Success(Unit)

        viewModel.onConfirmBlock()

        coVerify { relationRepository.block(9) }
        assertTrue(viewModel.uiState.value.blocked)
        assertFalse(viewModel.uiState.value.blockConfirmVisible)
    }

    @Test
    fun `拉黑失败不回上一页`() {
        val viewModel = loaded()
        coEvery { relationRepository.block(9) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        viewModel.onConfirmBlock()

        assertFalse(viewModel.uiState.value.blocked)
        assertEquals(NETWORK_ERROR_MESSAGE, viewModel.uiState.value.toast)
    }

    // ---------- 举报 ----------

    @Test
    fun `没选原因不让提交`() {
        val viewModel = loaded()

        viewModel.onSubmitReport()

        assertEquals("请选择举报原因", viewModel.uiState.value.toast)
    }

    @Test
    fun `提交举报成功提示服务端文案并清空弹窗`() {
        coEvery { noticeRepository.report(any(), any(), any(), any()) } returns ApiResult.Success(
            ReportResult(id = 1, statusText = "举报已提交，我们会尽快处理"),
        )
        val viewModel = loaded()
        viewModel.onReportVisibleChange(true)
        viewModel.onReportReasonChange(ReportReason.FRAUD)
        viewModel.onReportContentChange("一上来就让我加微信转账")

        viewModel.onSubmitReport()

        assertEquals("举报已提交，我们会尽快处理", viewModel.uiState.value.toast)
        assertFalse(viewModel.uiState.value.reportVisible)
        assertNull(viewModel.uiState.value.reportReason)
        assertEquals("", viewModel.uiState.value.reportContent)
        coVerify(exactly = 1) { noticeRepository.report(9, "FRAUD", "一上来就让我加微信转账", emptyList()) }
    }

    @Test
    fun `举报失败把服务端的话原样提示，弹窗留着`() {
        coEvery { noticeRepository.report(any(), any(), any(), any()) } returns
            ApiResult.BizError(40001, "不能举报自己")
        val viewModel = loaded()
        viewModel.onReportVisibleChange(true)
        viewModel.onReportReasonChange(ReportReason.OTHER)

        viewModel.onSubmitReport()

        assertEquals("不能举报自己", viewModel.uiState.value.toast)
        assertTrue("失败了弹窗还在，可以改", viewModel.uiState.value.reportVisible)
        assertFalse(viewModel.uiState.value.reportSubmitting)
    }

    @Test
    fun `关掉举报弹窗会清掉已填内容`() {
        val viewModel = loaded()
        viewModel.onReportVisibleChange(true)
        viewModel.onReportReasonChange(ReportReason.PORN)
        viewModel.onReportContentChange("有问题")

        viewModel.onReportVisibleChange(false)

        assertNull(viewModel.uiState.value.reportReason)
        assertEquals("", viewModel.uiState.value.reportContent)
    }

    // ---------- 相册预览 ----------

    @Test
    fun `点相册打开大图`() {
        val viewModel = loaded()

        viewModel.onPreview(1)

        assertEquals(1, viewModel.uiState.value.previewIndex)

        viewModel.onPreview(null)

        assertNull(viewModel.uiState.value.previewIndex)
    }

    @Test
    fun `相册地址按顺序给预览用`() {
        val withPhotos = detail().copy(
            photos = listOf(
                UserPhoto(1, "https://cdn/1.png"),
                UserPhoto(2, "https://cdn/2.png"),
            ),
        )
        val state = loaded(withPhotos).uiState.value

        assertEquals(listOf("https://cdn/1.png", "https://cdn/2.png"), state.photoUrls)
        // 封面用第一张相册
        assertEquals("https://cdn/1.png", state.detail?.coverUrl)
    }
}
