package com.qichuang.changliao.feature.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.data.local.ClDataStore
import com.qichuang.changliao.core.data.repository.AuthRepository
import com.qichuang.changliao.core.data.repository.UserRepository
import com.qichuang.changliao.core.network.ApiResult
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * 设置（P15）状态管理。
 *
 * 三个开关/动作的口径：
 * - **消息提醒**：本地开关，写 DataStore 就行（`docs/01` P15 说的是「本地保存」）；
 * - **展示给附近的人**：服务端开关，**失败要切回来**（不能骗用户已经关了）；
 * - **改密码 / 注销**：业务错误（10006 旧密码错、10004 已注销…）原样提示。
 *
 * 注销成功之后本地 token 会被清掉（`AuthRepository::cancelAccount`），
 * 导航层监听到没 token 就回登录页，这里不用管跳转。
 */
@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val clDataStore: ClDataStore,
    private val userRepository: UserRepository,
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(SettingsUiState())

    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            clDataStore.reminderEnabled.collect { enabled ->
                _uiState.update { it.copy(loading = false, reminderEnabled = enabled) }
            }
        }
        _uiState.update {
            it.copy(showInNearby = userRepository.currentUser.value?.showInNearby != 0)
        }
    }

    // ---------- 两个开关 ----------

    fun onReminderChange(enabled: Boolean) {
        // 本地开关：立刻生效，不用等任何东西
        clDataStore.setReminderEnabled(enabled)
    }

    fun onShowInNearbyChange(show: Boolean) {
        if (_uiState.value.switching) {
            return
        }
        _uiState.update { it.copy(showInNearby = show, switching = true) }
        viewModelScope.launch {
            when (val result = userRepository.updateSettings(showInNearby = if (show) 1 else 0)) {
                is ApiResult.Success -> {
                    _uiState.update { it.copy(switching = false, toast = if (show) "已展示给附近的人" else "已从同城列表隐藏") }
                    // 让「我」页面的资料也跟上（那边的流读的是 currentUser）
                    userRepository.me()
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(switching = false, showInNearby = !show, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(switching = false, showInNearby = !show, toast = result.message)
                }
            }
        }
    }

    // ---------- 修改密码 ----------

    fun onPasswordSheetVisibleChange(visible: Boolean) {
        _uiState.update {
            it.copy(
                passwordSheetVisible = visible,
                // 关掉就把输入清干净，别留着上次的半截密码
                oldPassword = "",
                newPassword = "",
                confirmPassword = "",
                passwordError = null,
            )
        }
    }

    fun onOldPasswordChange(value: String) = _uiState.update { it.copy(oldPassword = value.take(PASSWORD_MAX)) }

    fun onNewPasswordChange(value: String) = _uiState.update { it.copy(newPassword = value.take(PASSWORD_MAX)) }

    fun onConfirmPasswordChange(value: String) = _uiState.update { it.copy(confirmPassword = value.take(PASSWORD_MAX)) }

    fun onSubmitPassword() {
        val state = _uiState.value
        if (!state.canSubmitPassword) {
            return
        }

        _uiState.update { it.copy(submittingPassword = true) }
        viewModelScope.launch {
            when (val result = authRepository.changePassword(state.oldPassword, state.newPassword)) {
                is ApiResult.Success -> _uiState.update {
                    it.copy(
                        submittingPassword = false,
                        passwordSheetVisible = false,
                        oldPassword = "",
                        newPassword = "",
                        confirmPassword = "",
                        toast = "密码已修改",
                    )
                }

                // 10006 旧密码错，直接用服务端的文案
                is ApiResult.BizError -> _uiState.update {
                    it.copy(submittingPassword = false, passwordError = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(submittingPassword = false, passwordError = result.message)
                }
            }
        }
    }

    // ---------- 注销与退出 ----------

    fun onCancelConfirmVisibleChange(visible: Boolean) {
        _uiState.update { it.copy(cancelConfirmVisible = visible) }
    }

    fun onConfirmCancelAccount() {
        if (_uiState.value.cancelSubmitting) {
            return
        }

        _uiState.update { it.copy(cancelSubmitting = true) }
        viewModelScope.launch {
            when (val result = authRepository.cancelAccount()) {
                // 成功之后 token 已经清掉了，导航层会把用户送回登录页
                is ApiResult.Success -> _uiState.update {
                    it.copy(cancelSubmitting = false, cancelConfirmVisible = false)
                }

                is ApiResult.BizError -> _uiState.update {
                    it.copy(cancelSubmitting = false, cancelConfirmVisible = false, toast = result.message)
                }

                is ApiResult.NetworkError -> _uiState.update {
                    it.copy(cancelSubmitting = false, cancelConfirmVisible = false, toast = result.message)
                }
            }
        }
    }

    /** 退出登录：服务端拉黑 token + 本地清掉，导航层负责回登录页。 */
    fun onLogout() {
        viewModelScope.launch { authRepository.logout() }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    companion object {
        /** 和服务端的密码规则一致（8~20 位）。 */
        const val PASSWORD_MAX = 20
    }
}
