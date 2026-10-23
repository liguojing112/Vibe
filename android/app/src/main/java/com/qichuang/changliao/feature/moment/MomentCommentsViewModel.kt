package com.qichuang.changliao.feature.moment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.MomentRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** 评论弹窗：分页加载 + 发送。 */
@HiltViewModel
class MomentCommentsViewModel @Inject constructor(
    private val momentRepository: MomentRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(MomentCommentsUiState())

    val uiState: StateFlow<MomentCommentsUiState> = _uiState.asStateFlow()

    /** 打开某条动态的评论（换了动态就重置）。 */
    fun load(momentId: Long) {
        if (_uiState.value.momentId == momentId) {
            return
        }
        _uiState.value = MomentCommentsUiState(momentId = momentId)
        fetch(page = 1, append = false)
    }

    fun onRefresh() {
        fetch(page = 1, append = false)
    }

    fun onLoadMore() {
        val state = _uiState.value
        if (state.loadingMore || !state.hasMore) {
            return
        }
        _uiState.update { it.copy(loadingMore = true) }
        fetch(state.nextPage, append = true)
    }

    fun onInputChange(value: String) {
        _uiState.update { it.copy(input = value) }
    }

    fun onSend() {
        val state = _uiState.value
        if (!state.canSend) {
            return
        }
        _uiState.update { it.copy(sending = true) }

        viewModelScope.launch {
            when (val result = momentRepository.comment(state.momentId, state.input)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        comments = it.comments + result.data,
                        total = it.total + 1,
                        input = "",
                        sending = false,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(sending = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(sending = false, toast = result.message) }
            }
        }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private fun fetch(page: Int, append: Boolean) {
        val momentId = _uiState.value.momentId
        viewModelScope.launch {
            when (val result = momentRepository.comments(momentId, page)) {
                is ApiResult.Success -> _uiState.update { state ->
                    state.copy(
                        comments = if (append) state.comments + result.data.list else result.data.list,
                        total = result.data.total,
                        page = result.data.page,
                        hasMore = result.data.hasMore,
                        loading = false,
                        loadingMore = false,
                        error = null,
                    )
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(loading = false, loadingMore = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(loading = false, loadingMore = false, toast = result.message)
                }
            }
        }
    }
}
