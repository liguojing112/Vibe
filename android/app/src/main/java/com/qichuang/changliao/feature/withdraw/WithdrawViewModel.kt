package com.qichuang.changliao.feature.withdraw

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.model.WithdrawAccountType
import com.qichuang.changliao.core.data.repository.WithdrawRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 收益提现（P28）状态管理。
 *
 * 提交成功后**重新拉一次 info**：可提现金额、今日剩余次数都变了；
 * 记录也从头拉一遍（新提交的那条排在最前）。
 *
 * 失败按业务码原样提示：`20002` 积分不足、`40001` 未实名/档位不对/次数用完 ——
 * 这些判断都在服务端（页面只负责把话转出来）。
 */
@HiltViewModel
class WithdrawViewModel @Inject constructor(
    private val withdrawRepository: WithdrawRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(WithdrawUiState())

    val uiState: StateFlow<WithdrawUiState> = _uiState.asStateFlow()

    private var loaded = false

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        load()
    }

    fun onSelectTier(yuan: Int) {
        _uiState.update { it.copy(selectedYuan = yuan) }
    }

    fun onAccountTypeChange(type: WithdrawAccountType) {
        _uiState.update { it.copy(accountType = type) }
    }

    fun onAccountNoChange(value: String) {
        _uiState.update { it.copy(accountNo = value.take(ACCOUNT_NO_MAX_LENGTH)) }
    }

    fun onBankNameChange(value: String) {
        _uiState.update { it.copy(bankName = value.take(BANK_NAME_MAX_LENGTH)) }
    }

    fun onSubmit() {
        val state = _uiState.value
        val yuan = state.selectedYuan
        if (!state.canSubmit || yuan == null) {
            return
        }

        _uiState.update { it.copy(submitting = true) }
        viewModelScope.launch {
            val result = withdrawRepository.apply(
                amountYuan = yuan,
                accountType = state.accountType,
                accountNo = state.accountNo.trim(),
                bankName = state.bankName.trim(),
            )

            when (result) {
                is ApiResult.Success -> {
                    _uiState.update { it.copy(submitting = false, toast = "已提交，等待审核") }
                    // 金额、次数、记录都变了，重新拉一遍
                    load()
                }

                is ApiResult.BizError -> _uiState.update { it.copy(submitting = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(submitting = false, toast = result.message) }
            }
        }
    }

    fun onLoadMoreRecords() {
        val state = _uiState.value
        if (state.recordsLoading || !state.hasMoreRecords) {
            return
        }

        _uiState.update { it.copy(recordsLoading = true) }
        viewModelScope.launch {
            when (val result = withdrawRepository.records(state.nextRecordsPage)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        recordsLoading = false,
                        records = it.records + result.data,
                        hasMoreRecords = result.data.size >= WithdrawUiState.PAGE_SIZE,
                        recordsPage = it.nextRecordsPage,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(recordsLoading = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(recordsLoading = false, toast = result.message) }
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
        _uiState.update { it.copy(loading = it.info == null, error = null) }
        viewModelScope.launch {
            when (val result = withdrawRepository.info()) {
                is ApiResult.Success -> {
                    val info = result.data
                    _uiState.update {
                        it.copy(
                            loading = false,
                            error = null,
                            info = info,
                            // 默认选中第一个「积分够」的档位；一个都不够就保持空（按钮点不了）
                            selectedYuan = it.selectedYuan ?: info.tiers.firstOrNull { tier -> tier.enabled }?.yuan,
                            // 上次填的收款信息自动带出来（`docs/01` P28）
                            accountType = info.account?.accountType ?: it.accountType,
                            accountNo = info.account?.accountNo ?: it.accountNo,
                            bankName = info.account?.bankName ?: it.bankName,
                        )
                    }
                    loadRecords()
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }

    /** 记录从第一页重拉（提交成功之后也要重拉）。 */
    private suspend fun loadRecords() {
        when (val result = withdrawRepository.records(1)) {
            is ApiResult.Success -> _uiState.update {
                it.copy(
                    records = result.data,
                    hasMoreRecords = result.data.size >= WithdrawUiState.PAGE_SIZE,
                    recordsPage = 1,
                )
            }

            // 记录拉不到不整页报错：表单还能用
            is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
            is ApiResult.NetworkError -> Unit
        }
    }

    companion object {
        private const val ACCOUNT_NO_MAX_LENGTH = 64

        private const val BANK_NAME_MAX_LENGTH = 64
    }
}
