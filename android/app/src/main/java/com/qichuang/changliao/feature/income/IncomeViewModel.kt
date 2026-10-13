package com.qichuang.changliao.feature.income

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.IncomeRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 我的收益（P26）状态管理：大卡 + 今日三项 + 收益明细分页。
 *
 * 明细**分页拉**（一页 20 条，和服务端一致），`has_more` 用「这一页是不是满的」判断 ——
 * 服务端的分页返回里没有这个字段，满页就认为可能还有下一页。
 */
@HiltViewModel
class IncomeViewModel @Inject constructor(
    private val incomeRepository: IncomeRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(IncomeUiState())

    val uiState: StateFlow<IncomeUiState> = _uiState.asStateFlow()

    private var loaded = false

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        load()
    }

    /** 加载下一页明细。 */
    fun onLoadMoreLogs() {
        val state = _uiState.value
        if (state.logsLoading || !state.hasMoreLogs) {
            return
        }

        _uiState.update { it.copy(logsLoading = true) }
        viewModelScope.launch {
            when (val result = incomeRepository.logs(state.nextPage)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        logsLoading = false,
                        logs = it.logs + result.data,
                        hasMoreLogs = result.data.size >= IncomeUiState.PAGE_SIZE,
                        page = it.nextPage,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(logsLoading = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(logsLoading = false, toast = result.message) }
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
            when (val summary = incomeRepository.summary()) {
                is ApiResult.Success -> {
                    _uiState.update { it.copy(loading = false, error = null, summary = summary.data) }
                    loadLogs()
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = summary.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = summary.message) }
            }
        }
    }

    /** 第一页明细；拉不到不整页报错（大卡已经出来了，明细空了就空了）。 */
    private suspend fun loadLogs() {
        when (val result = incomeRepository.logs(1)) {
            is ApiResult.Success -> _uiState.update {
                it.copy(
                    logs = result.data,
                    hasMoreLogs = result.data.size >= IncomeUiState.PAGE_SIZE,
                    page = 1,
                )
            }

            is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
            is ApiResult.NetworkError -> Unit
        }
    }
}
