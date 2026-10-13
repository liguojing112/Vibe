package com.qichuang.changliao.feature.task

import com.qichuang.changliao.core.data.model.DailyTask
import com.qichuang.changliao.core.data.model.TaskReward
import com.qichuang.changliao.core.data.repository.TaskRepository
import com.qichuang.changliao.core.network.ApiResult
import com.qichuang.changliao.core.network.NETWORK_ERROR_MESSAGE
import io.mockk.coEvery
import io.mockk.coVerify
import io.mockk.mockk
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.awaitCancellation
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
 * 每日任务（P11）：拉列表、签到、领奖、失败原样报出来，以及
 * **签到/领奖成功之后要重新拉一次列表**（状态由服务端说了算，本地不推）。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class DailyTaskViewModelTest {

    private val taskRepository = mockk<TaskRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { taskRepository.list() } returns ApiResult.Success(tasks())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = DailyTaskViewModel(taskRepository).also { it.enter() }

    private fun tasks() = listOf(
        DailyTask(
            id = 1,
            name = "每日签到",
            description = "连续签到可领取玫瑰",
            icon = "📅",
            type = "SIGN_IN",
            target = 1,
            progress = 0,
            rewardText = "+5 金币",
            status = DailyTask.STATUS_TODO,
            buttonText = "签到",
            action = DailyTask.ACTION_SIGN_IN,
        ),
        DailyTask(
            id = 7,
            name = "和新朋友打招呼",
            description = "今天向 3 位新朋友发出问候",
            icon = "💬",
            type = "SEND_MESSAGE",
            target = 3,
            progress = 1,
            rewardText = "+5 金币",
            status = DailyTask.STATUS_TODO,
            buttonText = "去认识",
            action = DailyTask.ACTION_DISCOVER,
        ),
        DailyTask(
            id = 3,
            name = "完善照片",
            description = "上传 3 张生活照",
            icon = "📷",
            target = 3,
            progress = 3,
            rewardText = "+20 金币",
            status = DailyTask.STATUS_CLAIMABLE,
            buttonText = "领取",
            action = DailyTask.ACTION_CLAIM,
        ),
    )

    @Test
    fun `进页面拿到任务列表`() {
        val state = viewModel().uiState.value

        assertEquals(3, state.tasks.size)
        assertEquals("每日签到", state.tasks[0].name)
        assertEquals("📅", state.tasks[0].icon)
        assertEquals("签到", state.tasks[0].buttonText)
        assertFalse(state.loading)
        assertNull(state.error)
    }

    @Test
    fun `横幅文案来自设计图`() {
        val state = viewModel().uiState.value

        assertEquals("今天也有好事发生 ✨", state.bannerTitle)
        assertEquals("完成任务，收集玫瑰，让更多同频的人看见你。", state.bannerDesc)
    }

    @Test
    fun `进度文案只有目标大于 1 才显示`() {
        val state = viewModel().uiState.value

        assertEquals("签到目标是 1，不显示 0/1", "", state.tasks[0].progressText)
        assertEquals("1/3", state.tasks[1].progressText)
        assertEquals("3/3", state.tasks[2].progressText)
        assertTrue(state.tasks[2].claimable)
        assertFalse(state.tasks[2].claimed)
        assertEquals(1, state.claimableCount)
    }

    /** 已领过的任务按钮变灰不可点（`ClTaskCard` 的 `done`）。 */
    @Test
    fun `已领过的任务在界面上是已完成的灰按钮`() {
        coEvery { taskRepository.list() } returns ApiResult.Success(
            listOf(tasks()[0].copy(status = DailyTask.STATUS_CLAIMED, buttonText = "已完成")),
        )

        val task = viewModel().uiState.value.tasks.single()

        assertTrue(task.claimed)
        assertEquals("已完成", task.buttonText)
    }

    @Test
    fun `重复进页面不重复拉数据`() {
        val vm = viewModel()

        vm.enter()

        coVerify(exactly = 1) { taskRepository.list() }
    }

    /** 签到成功后要**重新拉一次列表**：状态由服务端说了算，本地不推。 */
    @Test
    fun `签到成功后用服务端的奖励文案提示，并重新拉列表`() {
        val vm = viewModel()
        coEvery { taskRepository.signIn() } returns ApiResult.Success(
            TaskReward(reward = 5, rewardText = "+5 金币", balance = 455),
        )

        vm.onSignIn()

        assertEquals("已领取 +5 金币", vm.uiState.value.toast)
        assertFalse(vm.uiState.value.acting)
        coVerify(exactly = 2) { taskRepository.list() }
    }

    @Test
    fun `领奖走的是对应的任务 id，女号拿积分文案`() {
        val vm = viewModel()
        coEvery { taskRepository.claim(3) } returns ApiResult.Success(
            TaskReward(reward = 50, rewardType = 2, rewardText = "+50 积分", balance = 50),
        )

        vm.onClaim(3)

        coVerify(exactly = 1) { taskRepository.claim(3) }
        assertEquals("已领取 +50 积分", vm.uiState.value.toast)
    }

    @Test
    fun `领奖失败时把服务端的话原样提示，不重新拉列表`() {
        val vm = viewModel()
        coEvery { taskRepository.claim(3) } returns ApiResult.BizError(40001, "任务还没完成")

        vm.onClaim(3)

        assertEquals("任务还没完成", vm.uiState.value.toast)
        coVerify(exactly = 1) { taskRepository.list() }
    }

    @Test
    fun `网络失败提示网络错误`() {
        val vm = viewModel()
        coEvery { taskRepository.signIn() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        vm.onSignIn()

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
        assertFalse(vm.uiState.value.acting)
    }

    /** 请求还没回来时再点一次不该再发一个请求（按钮虽然会变灰，但连点还是要挡住）。 */
    @Test
    fun `正在操作时忽略重复点击`() {
        val vm = viewModel()
        coEvery { taskRepository.claim(any()) } coAnswers { awaitCancellation() }

        vm.onClaim(3)
        assertTrue(vm.uiState.value.acting)
        vm.onClaim(3)

        coVerify(exactly = 1) { taskRepository.claim(3) }
    }

    @Test
    fun `任务列表为空时给空状态`() {
        coEvery { taskRepository.list() } returns ApiResult.Success(emptyList())

        val state = viewModel().uiState.value

        assertTrue(state.isEmpty)
        assertEquals(0, state.claimableCount)
    }

    @Test
    fun `列表拉不到时整页报错，重试再拉一次`() {
        coEvery { taskRepository.list() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { taskRepository.list() } returns ApiResult.Success(tasks())
        vm.onRetry()

        assertEquals(3, vm.uiState.value.tasks.size)
        assertNull(vm.uiState.value.error)
    }
}
