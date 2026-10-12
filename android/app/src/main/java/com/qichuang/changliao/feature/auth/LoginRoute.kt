package com.qichuang.changliao.feature.auth

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import com.qichuang.changliao.core.data.model.Agreement

/**
 * 把 [LoginViewModel] 和 [LoginScreen] 连起来，并负责跳转。
 */
@Composable
fun LoginRoute(
    onGoMain: () -> Unit,
    onRegister: () -> Unit,
    onOpenAgreement: (String) -> Unit,
    viewModel: LoginViewModel = hiltViewModel(),
) {
    val state by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(viewModel) {
        viewModel.goMain.collect { onGoMain() }
    }

    LoginScreen(
        state = state,
        onAccountChange = viewModel::onAccountChange,
        onPasswordChange = viewModel::onPasswordChange,
        onAgreedChange = viewModel::onAgreedChange,
        onSubmit = viewModel::submit,
        onRegister = onRegister,
        onUserAgreement = { onOpenAgreement(Agreement.TYPE_USER) },
        onPrivacy = { onOpenAgreement(Agreement.TYPE_PRIVACY) },
        onToastShown = viewModel::onToastShown,
    )
}
