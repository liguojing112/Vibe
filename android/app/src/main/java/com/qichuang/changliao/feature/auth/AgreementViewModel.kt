package com.qichuang.changliao.feature.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.common.UiState
import com.qichuang.changliao.core.data.model.Agreement
import com.qichuang.changliao.core.data.repository.AuthRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 协议页（docs/01 P00）：文本从接口取（后台可编辑），必须包含付费聊天声明（`00` 铁律 13）。
 *
 * 内容为空也算 `Empty`，避免显示一片空白还以为是加载失败。
 */
@HiltViewModel
class AgreementViewModel @Inject constructor(
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow<UiState<Agreement>>(UiState.Loading)
    val uiState: StateFlow<UiState<Agreement>> = _uiState.asStateFlow()

    private var loadedType: String? = null

    fun load(type: String) {
        if (loadedType == type && _uiState.value is UiState.Content) {
            return
        }
        loadedType = type
        _uiState.value = UiState.Loading

        viewModelScope.launch {
            _uiState.value = when (val result = authRepository.agreement(type)) {
                is ApiResult.Success -> {
                    if (result.data.content.isBlank()) {
                        UiState.Empty
                    } else {
                        UiState.Content(result.data)
                    }
                }

                is ApiResult.BizError -> UiState.Error(result.message)
                is ApiResult.NetworkError -> UiState.Error(result.message)
            }
        }
    }
}
