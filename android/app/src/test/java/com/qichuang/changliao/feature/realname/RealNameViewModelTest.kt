package com.qichuang.changliao.feature.realname

import com.qichuang.changliao.core.data.model.RealNameInfo
import com.qichuang.changliao.core.data.repository.RealNameRepository
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
 * 实名认证（P29）：状态展示、表单可提交条件、提交成功/失败、被驳回可重填。
 *
 * 身份证的**格式和校验位不在客户端判** —— 这里只守住「18 位才让点提交」，
 * 真正的判断由服务端返回 40003，页面把话转出来。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class RealNameViewModelTest {

    private val repository = mockk<RealNameRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
        coEvery { repository.info() } returns ApiResult.Success(RealNameInfo(status = 0, statusText = "未认证", canSubmit = true))
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    private fun viewModel() = RealNameViewModel(repository).also { it.enter() }

    @Test
    fun `没提交过时显示表单`() {
        val state = viewModel().uiState.value

        assertTrue(state.showForm)
        assertEquals("未认证", state.info?.statusText)
        assertFalse("没填之前不能提交", state.canSubmit)
    }

    @Test
    fun `姓名和 18 位身份证都填了才能提交`() {
        val vm = viewModel()

        vm.onNameChange("张三")
        assertFalse(vm.uiState.value.canSubmit)

        vm.onIdCardChange("11010119900307123")
        assertFalse("17 位还不行", vm.uiState.value.canSubmit)

        vm.onIdCardChange("110101199003071233")
        assertTrue(vm.uiState.value.canSubmit)
    }

    @Test
    fun `身份证输入只留数字和 X，且最多 18 位`() {
        val vm = viewModel()

        vm.onIdCardChange("110-101-1990 abc x")

        assertEquals("1101011990X", vm.uiState.value.idCardInput)
    }

    @Test
    fun `提交成功用服务端返回的状态刷新，并清掉输入`() {
        val vm = viewModel()
        vm.onNameChange("张三")
        vm.onIdCardChange("110101199003071233")
        coEvery { repository.submit(any(), any()) } returns ApiResult.Success(
            RealNameInfo(
                status = RealNameInfo.STATUS_AUDITING,
                statusText = "审核中",
                realName = "张三",
                idCardMask = "110***********1233",
                canSubmit = false,
            ),
        )

        vm.onSubmit()

        val state = vm.uiState.value
        assertEquals("审核中", state.info?.statusText)
        assertEquals("110***********1233", state.info?.idCardMask)
        assertEquals("已提交，等待审核", state.toast)
        assertEquals("提交后清掉输入，只回显打码号", "", state.idCardInput)
        assertFalse(state.showForm)
    }

    /** 40003 格式错、40002 审核中不能重复提交：文案用服务端的，不要自己拼。 */
    @Test
    fun `服务端的业务错误原样提示`() {
        val vm = viewModel()
        vm.onNameChange("张三")
        vm.onIdCardChange("110101199003071233")
        coEvery { repository.submit(any(), any()) } returns ApiResult.BizError(40003, "实名信息格式错误")

        vm.onSubmit()

        assertEquals("实名信息格式错误", vm.uiState.value.toast)
        assertTrue("失败了表单还在，可以改", vm.uiState.value.showForm)
        assertFalse(vm.uiState.value.submitting)
    }

    @Test
    fun `网络失败提示网络错误`() {
        val vm = viewModel()
        vm.onNameChange("张三")
        vm.onIdCardChange("110101199003071233")
        coEvery { repository.submit(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)

        vm.onSubmit()

        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `被驳回时可以重新提交，并把上次的姓名带出来`() {
        coEvery { repository.info() } returns ApiResult.Success(
            RealNameInfo(
                status = RealNameInfo.STATUS_REJECTED,
                statusText = "已驳回",
                realName = "张三",
                idCardMask = "110***********1233",
                rejectReason = "照片不清晰",
                canSubmit = true,
            ),
        )

        val state = viewModel().uiState.value

        assertTrue(state.showForm)
        assertEquals("姓名可以带出来", "张三", state.nameInput)
        assertEquals("号码不能回显（服务端只给打码的）", "", state.idCardInput)
        assertEquals("照片不清晰", state.info?.rejectReason)
    }

    @Test
    fun `已通过时不显示表单`() {
        coEvery { repository.info() } returns ApiResult.Success(
            RealNameInfo(
                status = RealNameInfo.STATUS_PASSED,
                statusText = "已通过",
                realName = "张三",
                idCardMask = "110***********1233",
                canSubmit = false,
            ),
        )

        val state = viewModel().uiState.value

        assertFalse(state.showForm)
        assertTrue(state.info?.passed == true)
    }

    @Test
    fun `重复进页面不重复拉数据`() {
        val vm = viewModel()

        vm.enter()

        coVerify(exactly = 1) { repository.info() }
    }

    @Test
    fun `拉不到时整页报错，重试再拉一次`() {
        coEvery { repository.info() } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = viewModel()
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.error)

        coEvery { repository.info() } returns ApiResult.Success(RealNameInfo(canSubmit = true))
        vm.onRetry()

        assertNull(vm.uiState.value.error)
        assertTrue(vm.uiState.value.showForm)
    }
}
