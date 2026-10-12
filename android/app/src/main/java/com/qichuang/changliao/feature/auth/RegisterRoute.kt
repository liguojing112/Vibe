package com.qichuang.changliao.feature.auth

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle

/** 把 [RegisterViewModel] 和 [RegisterScreen] 连起来。 */
@Composable
fun RegisterRoute(
    onGoMain: () -> Unit,
    onBack: () -> Unit,
    viewModel: RegisterViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(viewModel) {
        viewModel.goMain.collect { onGoMain() }
    }

    RegisterScreen(
        state = state,
        onPhoneChange = viewModel::onPhoneChange,
        onPasswordChange = viewModel::onPasswordChange,
        onConfirmPasswordChange = viewModel::onConfirmPasswordChange,
        onGenderChange = viewModel::onGenderChange,
        onNicknameChange = viewModel::onNicknameChange,
        onSubmit = viewModel::submit,
        onBack = onBack,
        onToastShown = viewModel::onToastShown,
    )
}
