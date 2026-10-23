package com.qichuang.changliao.feature.moment

import android.net.Uri
import com.qichuang.changliao.core.data.model.Moment
import com.qichuang.changliao.core.data.repository.ImageUploader
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
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

/**
 * 发布动态：内容/图片校验、上传后再发布。
 */
@OptIn(ExperimentalCoroutinesApi::class)
class PublishMomentViewModelTest {

    private val repository = mockk<MomentRepository>()

    private val uploader = mockk<ImageUploader>()

    @Before
    fun setUp() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun tearDown() {
        Dispatchers.resetMain()
    }

    @Test
    fun `空内容且没有图片时不能发布`() {
        val vm = PublishMomentViewModel(repository, uploader)

        assertFalse(vm.uiState.value.canPublish)
    }

    @Test
    fun `有文字就可以发布`() {
        val vm = PublishMomentViewModel(repository, uploader)

        vm.onContentChange("今天很开心")

        assertTrue(vm.uiState.value.canPublish)
    }

    @Test
    fun `选图上传成功后图片列表增加`() {
        coEvery { uploader.upload(any(), any()) } returns ApiResult.Success("https://cdn/x.jpg")
        val vm = PublishMomentViewModel(repository, uploader)

        vm.onImagesPicked(listOf(mockk<Uri>(), mockk<Uri>()))

        assertEquals(2, vm.uiState.value.images.size)
        assertFalse(vm.uiState.value.uploading)
    }

    @Test
    fun `上传失败给出提示且不加入列表`() {
        coEvery { uploader.upload(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = PublishMomentViewModel(repository, uploader)

        vm.onImagesPicked(listOf(mockk<Uri>()))

        assertTrue(vm.uiState.value.images.isEmpty())
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `发布成功后标记 published`() {
        coEvery { repository.publish("你好", emptyList()) } returns ApiResult.Success(Moment(id = 1))
        val vm = PublishMomentViewModel(repository, uploader)
        vm.onContentChange("你好")

        vm.onPublish()

        assertTrue(vm.uiState.value.published)
        assertFalse(vm.uiState.value.publishing)
    }

    @Test
    fun `发布失败不标记 published`() {
        coEvery { repository.publish(any(), any()) } returns ApiResult.NetworkError(NETWORK_ERROR_MESSAGE)
        val vm = PublishMomentViewModel(repository, uploader)
        vm.onContentChange("你好")

        vm.onPublish()

        assertFalse(vm.uiState.value.published)
        assertEquals(NETWORK_ERROR_MESSAGE, vm.uiState.value.toast)
    }

    @Test
    fun `删除图片按下标移除`() {
        coEvery { uploader.upload(any(), any()) } returnsMany listOf(
            ApiResult.Success("a"),
            ApiResult.Success("b"),
        )
        val vm = PublishMomentViewModel(repository, uploader)
        vm.onImagesPicked(listOf(mockk<Uri>(), mockk<Uri>()))

        vm.onRemoveImage(0)

        assertEquals(listOf("b"), vm.uiState.value.images)
    }
}
