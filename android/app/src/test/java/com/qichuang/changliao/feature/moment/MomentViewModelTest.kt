package com.qichuang.changliao.feature.moment

import com.qichuang.changliao.core.data.model.Moment
import com.qichuang.changliao.core.data.model.MomentPage
import com.qichuang.changliao.core.data.repository.MomentRepository
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
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 朋友圈列表：加载、点赞乐观更新与回滚、删除。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class MomentViewModelTest {

    private val repository = mockk<MomentRepository>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `加载某人的动态`() {
        coEvery { repository.userMoments(7L, 1) } returns ApiResult.Success(
            MomentPage(list = listOf(moment(id = 1, userId = 7L)), total = 1, page = 1, hasMore = false),
        )
        val vm = MomentViewModel(repository)

        vm.enter(7L)

        assertEquals(1, vm.uiState.value.moments.size)
        assertFalse(vm.uiState.value.isMine)
        assertFalse(vm.uiState.value.loading)
    }

    @Test
    fun `userId 为 0 时走我的朋友圈`() {
        coEvery { repository.mine(1) } returns ApiResult.Success(MomentPage(total = 0, page = 1))

        val vm = MomentViewModel(repository)
        vm.enter(0L)

        assertTrue(vm.uiState.value.isMine)
        assertEquals("我的朋友圈", vm.uiState.value.title)
        assertTrue(vm.uiState.value.isEmpty)
    }

    @Test
    fun `点赞乐观更新并用服务端计数校准`() {
        coEvery { repository.userMoments(7L, 1) } returns ApiResult.Success(
            MomentPage(list = listOf(moment(id = 1, userId = 7L, likeCount = 2)), total = 1, page = 1),
        )
        coEvery { repository.like(1L) } returns ApiResult.Success(
            com.qichuang.changliao.core.data.model.MomentLikeResult(liked = true, likeCount = 5),
        )
        val vm = MomentViewModel(repository)
        vm.enter(7L)

        vm.onToggleLike(vm.uiState.value.moments.first())

        val updated = vm.uiState.value.moments.first()
        assertTrue(updated.isLiked)
        assertEquals(5, updated.likeCount)
    }

    @Test
    fun `点赞失败要回滚`() {
        coEvery { repository.userMoments(7L, 1) } returns ApiResult.Success(
            MomentPage(list = listOf(moment(id = 1, userId = 7L, likeCount = 2)), total = 1, page = 1),
        )
        coEvery { repository.like(1L) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = MomentViewModel(repository)
        vm.enter(7L)

        vm.onToggleLike(vm.uiState.value.moments.first())

        val restored = vm.uiState.value.moments.first()
        assertFalse(restored.isLiked)
        assertEquals(2, restored.likeCount)
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `删除成功后从列表移出`() {
        coEvery { repository.userMoments(7L, 1) } returns ApiResult.Success(
            MomentPage(list = listOf(moment(id = 1, userId = 7L)), total = 1, page = 1),
        )
        coEvery { repository.remove(1L) } returns ApiResult.Success(Unit)
        val vm = MomentViewModel(repository)
        vm.enter(7L)

        vm.onDelete(vm.uiState.value.moments.first())

        assertTrue(vm.uiState.value.moments.isEmpty())
    }

    @Test
    fun `预览打开与关闭`() {
        coEvery { repository.userMoments(7L, 1) } returns ApiResult.Success(MomentPage())
        val vm = MomentViewModel(repository)
        vm.enter(7L)

        vm.onPreview(listOf("a", "b"), 1)
        assertEquals(1, vm.uiState.value.previewIndex)
        assertEquals(listOf("a", "b"), vm.uiState.value.previewUrls)

        vm.onClosePreview()
        assertNull(vm.uiState.value.previewIndex)
    }

    @Test
    fun `评论数变化同步到卡片`() {
        coEvery { repository.userMoments(7L, 1) } returns ApiResult.Success(
            MomentPage(list = listOf(moment(id = 1, userId = 7L, commentCount = 0)), total = 1, page = 1),
        )
        val vm = MomentViewModel(repository)
        vm.enter(7L)

        vm.onCommentsCountChanged(1L, 3)

        assertEquals(3, vm.uiState.value.moments.first().commentCount)
        assertNotNull(vm.uiState.value.moments.first())
    }

    private fun moment(
        id: Long,
        userId: Long,
        likeCount: Int = 0,
        commentCount: Int = 0,
    ) = Moment(
        id = id,
        userId = userId,
        nickname = "用户$userId",
        content = "内容",
        likeCount = likeCount,
        commentCount = commentCount,
        isLiked = false,
        canDelete = false,
        createdAt = 0L,
    )
}
