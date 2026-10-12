package com.qichuang.changliao.feature.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.repository.AuthRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.receiveAsFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 登录页（docs/01 P00）。
 *
 * 校验规则与 `server/app/Http/Api/Request/V1` 下的请求类一致，但**只做「少一次往返」的本地拦截**：
 * 10001（账号或密码错误）、10003（锁定 N 分钟）、10004（封禁原因与解封时间）这些带具体信息的提示，
 * 一律直接用服务端返回的 `message` 显示（docs/03 第 6 节第 5 条）。
 */
@HiltViewModel
class LoginViewModel @Inject constructor(
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    private val _goMain = Channel<Unit>(Channel.BUFFERED)

    /** 登录成功、可以进主界面了。跳转放这里而不是状态里，避免重组时重复触发。 */
    val goMain: Flow<Unit> = _goMain.receiveAsFlow()

    fun onAccountChange(value: String) {
        _uiState.update { it.copy(account = value, accountError = null) }
    }

    fun onPasswordChange(value: String) {
        _uiState.update { it.copy(password = value, passwordError = null) }
    }

    fun onAgreedChange(value: Boolean) {
        _uiState.update { it.copy(agreed = value, agreementError = null) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    fun submit() {
        val state = _uiState.value
        if (state.submitting) {
            return
        }

        val accountError = if (state.account.isBlank()) "请输入手机号或账号" else null
        val passwordError = if (state.password.isBlank()) "请输入密码" else null
        val agreementError = if (!state.agreed) "请先阅读并同意《用户协议》《隐私政策》" else null
        if (accountError != null || passwordError != null || agreementError != null) {
            _uiState.update {
                it.copy(
                    accountError = accountError,
                    passwordError = passwordError,
                    agreementError = agreementError,
                )
            }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(submitting = true) }
            val result = authRepository.login(state.account.trim(), state.password)
            _uiState.update { it.copy(submitting = false) }

            when (result) {
                is ApiResult.Success -> _goMain.send(Unit)
                is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(toast = result.message) }
            }
        }
    }
}
