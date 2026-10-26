package com.qichuang.changliao.feature.moment

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.component.ClPhotoGrid
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.ClTopBar
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 发表动态：正文 + 最多 9 张图。
 *
 * 选完图立刻上传，`ClPhotoGrid` 里显示的是**上传后的地址** ——
 * 所以「正在上传」时发布按钮是禁用的，避免把还没传完的图漏掉。
 */
@Composable
fun PublishMomentScreen(
    state: PublishMomentUiState,
    onBack: () -> Unit,
    onContentChange: (String) -> Unit,
    onImagesPicked: (List<android.net.Uri>) -> Unit,
    onRemoveImage: (Int) -> Unit,
    onPublish: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val remaining = PublishMomentUiState.MAX_IMAGES - state.images.size
    val picker = rememberLauncherForActivityResult(
        ActivityResultContracts.PickMultipleVisualMedia(PublishMomentUiState.MAX_IMAGES),
    ) { uris -> if (uris.isNotEmpty()) onImagesPicked(uris) }

    ClScaffold(
        modifier = modifier,
        containerColor = ClTheme.colors.bgPage,
        topBar = {
            ClTopBar(
                title = "发表动态",
                onBack = onBack,
                action = {
                    ClPrimaryButton(
                        text = "发布",
                        fillMaxWidth = false,
                        enabled = state.canPublish,
                        loading = state.publishing,
                        onClick = onPublish,
                    )
                },
            )
        },
    ) {
        // Toast 宿主由 ClScaffold 提供，必须在这里面取（外面拿到的是兜底实例，永远不会显示）
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .verticalScroll(rememberScrollState())
                .padding(ClTheme.spacing.l),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.m),
        ) {
            ClTextField(
                label = "这一刻的想法",
                value = state.content,
                onValueChange = onContentChange,
                placeholder = "说点什么…",
                singleLine = false,
                maxLength = PublishMomentUiState.MAX_CONTENT,
            )

            ClPhotoGrid(
                urls = state.images,
                editable = true,
                maxCount = PublishMomentUiState.MAX_IMAGES,
                onAdd = {
                    if (remaining <= 0) {
                        toast.show("最多 9 张图片")
                    } else {
                        picker.launch(
                            PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly),
                        )
                    }
                },
                onRemove = onRemoveImage,
            )
        }
    }
}
