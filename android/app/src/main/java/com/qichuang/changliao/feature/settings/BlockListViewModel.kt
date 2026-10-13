package com.qichuang.changliao.feature.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.RelationRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 黑名单（P15）状态管理：拉列表 + 解除拉黑。
 *
 * 解除拉黑之后**本地就把这个人去掉**，不重新拉整页 —— 服务端那边是幂等的，
 * 而且重新拉会让列表跳一下（用户刚点完，看到刷新动画反而以为没成功）。
 */
@HiltViewModel
class BlockListViewModel @Inject constructor(
    private val relationRepository: RelationRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(BlockListUiState())

    val uiState: StateFlow<BlockListUiState> = _uiState.asStateFlow()

    private var loaded = false

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        load()
    }

    fun onRemove(targetId: Long) {
        if (_uiState.value.removingId != null) {
            return
        }

        _uiState.update { it.copy(removingId = targetId) }
        viewModelScope.launch {
            when (val result = relationRepository.unblock(targetId)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        removingId = null,
                        people = it.people.filterNot { person -> person.id == targetId },
                        toast = "已解除拉黑",
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(removingId = null, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(removingId = null, toast = result.message) }
            }
        }
    }

    fun onRetry() {
        loaded = false
        enter()
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    private fun load() {
        _uiState.update { it.copy(loading = true, error = null) }
        viewModelScope.launch {
            when (val result = relationRepository.blocks(page = 1)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(loading = false, error = null, people = result.data.list)
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }
}
