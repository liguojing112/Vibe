package com.qichuang.changliao.feature.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.qichuang.changliao.core.common.Validators
import com.qichuang.changliao.core.data.model.Gender
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
 * 注册页（docs/01 P00）：手机号 11 位、密码 6~20 位含字母和数字、确认密码一致、性别必选、昵称 2~12 字。
 *
 * 注册成功服务端直接发 token（和登录一样），所以成功即进主界面。
 */
@HiltViewModel
class RegisterViewModel @Inject constructor(
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val _uiState = MutableStateFlow(RegisterUiState())
    val uiState: StateFlow<RegisterUiState> = _uiState.asStateFlow()

    private val _goMain = Channel<Unit>(Channel.BUFFERED)
    val goMain: Flow<Unit> = _goMain.receiveAsFlow()

    fun onPhoneChange(value: String) {
        _uiState.update { it.copy(phone = value, phoneError = null) }
    }

    fun onPasswordChange(value: String) {
        _uiState.update { it.copy(password = value, passwordError = null, confirmPasswordError = null) }
    }

    fun onConfirmPasswordChange(value: String) {
        _uiState.update { it.copy(confirmPassword = value, confirmPasswordError = null) }
    }

    fun onGenderChange(value: Int) {
        _uiState.update { it.copy(gender = value, genderError = null) }
    }

    fun onNicknameChange(value: String) {
        _uiState.update { it.copy(nickname = value, nicknameError = null) }
    }

    fun onToastShown() {
        _uiState.update { it.copy(toast = null) }
    }

    fun submit() {
        val state = _uiState.value
        if (state.submitting) {
            return
        }

        val phoneError = if (Validators.isPhone(state.phone)) null else Validators.PHONE_INVALID
        val passwordError = if (Validators.isPassword(state.password)) null else Validators.PASSWORD_INVALID
        val confirmPasswordError = when {
            state.confirmPassword.isEmpty() -> "请再输入一次密码"
            state.confirmPassword != state.password -> "两次输入的密码不一致"
            else -> null
        }
        val genderError = if (state.gender == Gender.UNKNOWN) "请选择性别" else null
        val nicknameError = if (Validators.isNickname(state.nickname)) null else Validators.NICKNAME_INVALID

        if (phoneError != null || passwordError != null || confirmPasswordError != null ||
            genderError != null || nicknameError != null
        ) {
            _uiState.update {
                it.copy(
                    phoneError = phoneError,
                    passwordError = passwordError,
                    confirmPasswordError = confirmPasswordError,
                    genderError = genderError,
                    nicknameError = nicknameError,
                )
            }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(submitting = true) }
            val result = authRepository.register(
                phone = state.phone,
                password = state.password,
                confirmPassword = state.confirmPassword,
                gender = state.gender,
                nickname = state.nickname.trim(),
            )
            _uiState.update { it.copy(submitting = false) }

            when (result) {
                is ApiResult.Success -> _goMain.send(Unit)
                is ApiResult.BizError -> _uiState.update { it.copy(toast = result.message) }
                is ApiResult.NetworkError -> _uiState.update { it.copy(toast = result.message) }
            }
        }
    }
}
