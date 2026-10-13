package com.qichuang.changliao.feature.wallet

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.CoinLogTab
import com.qichuang.changliao.core.data.repository.WalletRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** 金币收支明细（P24）：三个 Tab + 分页。 */
@HiltViewModel
class CoinLogsViewModel @Inject constructor(
    private val walletRepository: WalletRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(CoinLogsUiState())

    val uiState: StateFlow<CoinLogsUiState> = _uiState.asStateFlow()

    private var page = 1

    init {
        loadBalance()
        loadFirstPage()
    }

    fun onTabSelected(tab: CoinLogTab) {
        if (tab == _uiState.value.tab) {
            return
        }
        _uiState.update { it.copy(tab = tab, entries = emptyList(), loading = true, error = null) }
        loadFirstPage()
    }

    fun onRefresh() {
        _uiState.update { it.copy(refreshing = true) }
        loadBalance()
        loadFirstPage()
    }

    fun onLoadMore() {
        if (_uiState.value.loadingMore || !_uiState.value.hasMore) {
            return
        }
        _uiState.update { it.copy(loadingMore = true, loadMoreError = null) }
        viewModelScope.launch {
            when (val result = walletRepository.coinLogs(_uiState.value.tab, page + 1)) {
                is ApiResult.Success -> {
                    page += 1
                    _uiState.update {
                        it.copy(
                            entries = it.entries + result.data.list,
                            hasMore = result.data.hasMore,
                            loadingMore = false,
                        )
                    }
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(loadingMore = false, loadMoreError = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(loadingMore = false, loadMoreError = result.message)
                }
            }
        }
    }

    fun onRetry() {
        _uiState.update { it.copy(loading = true, error = null) }
        loadFirstPage()
    }

    private fun loadFirstPage() {
        viewModelScope.launch {
            when (val result = walletRepository.coinLogs(_uiState.value.tab, 1)) {
                is ApiResult.Success -> {
                    page = 1
                    _uiState.update {
                        it.copy(
                            entries = result.data.list,
                            hasMore = result.data.hasMore,
                            loading = false,
                            refreshing = false,
                            error = null,
                            loadMoreError = null,
                        )
                    }
                }

                is ApiResult.BizError -> fail(result.message)
                is ApiResult.NetworkError -> fail(result.message)
            }
        }
    }

    private fun fail(message: String) {
        _uiState.update { it.copy(loading = false, refreshing = false, error = message) }
    }

    /** 顶部「当前余额」：明细列表里能看到「变动后余额」，但当前余额得单独拿一次。 */
    private fun loadBalance() {
        viewModelScope.launch {
            when (val result = walletRepository.coin()) {
                is ApiResult.Success -> _uiState.update { it.copy(balance = result.data.balance) }
                // 余额拿不到不影响明细本身，静默失败（下拉刷新会再试）
                is ApiResult.BizError -> Unit
                is ApiResult.NetworkError -> Unit
            }
        }
    }
}
