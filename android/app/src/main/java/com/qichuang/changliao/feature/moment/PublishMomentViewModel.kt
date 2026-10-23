package com.qichuang.changliao.feature.moment

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.ImageUploader
import com.qichuang.changliao.core.data.repository.MomentRepository
import com.qichuang.changliao.core.data.repository.UploadScene
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** 发布动态：选图先传上去拿地址，全部传完才允许发布。 */
@HiltViewModel
class PublishMomentViewModel @Inject constructor(
    private val momentRepository: MomentRepository,
    private val imageUploader: ImageUploader,
) : ViewModel() {

    private val _uiState = MutableStateFlow(PublishMomentUiState())

    val uiState: StateFlow<PublishMomentUiState> = _uiState.asStateFlow()

    fun onContentChange(value: String) {
        if (value.length > PublishMomentUiState.MAX_CONTENT) {
            return
        }
        _uiState.update { it.copy(content = value) }
    }

    fun onImagesPicked(uris: List<Uri>) {
        val remaining = PublishMomentUiState.MAX_IMAGES - _uiState.value.images.size
        if (remaining <= 0) {
            _uiState.update { it.copy(toast = "最多 9 张图片") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(uploading = true) }
            for (uri in uris.take(remaining)) {
                when (val result = imageUploader.upload(uri, UploadScene.MOMENT)) {
                    is ApiResult.Success ->
                        _uiState.update { it.copy(images = it.images + result.data) }

                    is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
                    is ApiResult.NetworkError -> _uiState.update { it.copy(toast = result.message) }
                }
            }
            _uiState.update { it.copy(uploading = false) }
        }
    }

    fun onRemoveImage(index: Int) {
        _uiState.update { state ->
            if (index !in state.images.indices) {
                state
            } else {
                state.copy(images = state.images.filterIndexed { i, _ -> i != index })
            }
        }
    }

    fun onPublish() {
        val state = _uiState.value
        if (!state.canPublish) {
            return
        }
        _uiState.update { it.copy(publishing = true) }

        viewModelScope.launch {
            when (val result = momentRepository.publish(state.content, state.images)) {
                is ApiResult.Success ->
                    _uiState.update { it.copy(publishing = false, published = true) }

                is ApiResult.BizError ->
                    _uiState.update { it.copy(publishing = false, toast = result.message) }

                is ApiResult.NetworkError ->
                    _uiState.update { it.copy(publishing = false, toast = result.message) }
            }
        }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }
}
