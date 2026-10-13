package com.qichuang.changliao.feature.realname

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.RealNameRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 实名认证（P29）状态管理。
 *
 * 提交之后**直接用服务端返回的状态**刷新页面（`/realname/submit` 的返回和 `/realname` 一样），
 * 不用再拉一次。
 *
 * 身份证的格式和校验位**不在客户端判**：客户端只数一下位数好让按钮可点，
 * 真正的判断在服务端（错了返回 40003，页面把服务端的话原样提示出来）。
 */
@HiltViewModel
class RealNameViewModel @Inject constructor(
    private val realNameRepository: RealNameRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(RealNameUiState())

    val uiState: StateFlow<RealNameUiState> = _uiState.asStateFlow()

    private var loaded = false

    fun enter() {
        if (loaded) {
            return
        }
        loaded = true
        load()
    }

    fun onNameChange(value: String) {
        _uiState.update { it.copy(nameInput = value.take(NAME_MAX_LENGTH)) }
    }

    fun onIdCardChange(value: String) {
        // 身份证只可能是数字和 X，输入时就挡掉别的字符
        val cleaned = value.uppercase().filter { it.isDigit() || it == 'X' }
        _uiState.update { it.copy(idCardInput = cleaned.take(RealNameUiState.ID_CARD_LENGTH)) }
    }

    fun onSubmit() {
        val state = _uiState.value
        if (!state.canSubmit) {
            return
        }

        _uiState.update { it.copy(submitting = true) }
        viewModelScope.launch {
            when (val result = realNameRepository.submit(state.nameInput.trim(), state.idCardInput.trim())) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        submitting = false,
                        info = result.data,
                        // 提交成功就把输入清掉：号码已经在服务端加密存了，页面只回显打码的
                        nameInput = "",
                        idCardInput = "",
                        toast = "已提交，等待审核",
                    )
                }

                // 40003 格式错、40002 审核中不能重复提交，文案直接用服务端的
                is ApiResult.BizError -> _uiState.update { it.copy(submitting = false, toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(submitting = false, toast = result.message) }
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
            when (val result = realNameRepository.info()) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        loading = false,
                        error = null,
                        info = result.data,
                        // 被驳回时把上次填的姓名带出来（号码不能回显，只能重填）
                        nameInput = if (result.data.rejected) result.data.realName else it.nameInput,
                    )
                }

                is ApiResult.BizError -> _uiState.update { it.copy(loading = false, error = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(loading = false, error = result.message) }
            }
        }
    }

    companion object {
        /** 姓名最多 32 字（和服务端 `real_name varchar(32)` 对齐）。 */
        const val NAME_MAX_LENGTH = 32
    }
}
