package com.qichuang.changliao.feature.auth

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import com.qichuang.changliao.core.designsystem.component.ClCheckboxAgreement
import com.qichuang.changliao.core.designsystem.component.ClPasswordField
import com.qichuang.changliao.core.designsystem.component.ClPrimaryButton
import com.qichuang.changliao.core.designsystem.component.ClScaffold
import com.qichuang.changliao.core.designsystem.component.ClTextButton
import com.qichuang.changliao.core.designsystem.component.ClTextField
import com.qichuang.changliao.core.designsystem.component.rememberClToast
import com.qichuang.changliao.core.designsystem.theme.ClTheme

/**
 * 登录页（docs/01 P00）。
 *
 * 只负责画界面：所有字段状态与校验都在 [LoginViewModel]。
 */
@Composable
fun LoginScreen(
    state: LoginUiState,
    onAccountChange: (String) -> Unit,
    onPasswordChange: (String) -> Unit,
    onAgreedChange: (Boolean) -> Unit,
    onSubmit: () -> Unit,
    onRegister: () -> Unit,
    onUserAgreement: () -> Unit,
    onPrivacy: () -> Unit,
    onToastShown: () -> Unit,
    modifier: Modifier = Modifier,
) {
    ClScaffold(modifier = modifier) {
        val toast = rememberClToast()
        LaunchedEffect(state.toast) {
            state.toast?.let {
                toast.show(it)
                onToastShown()
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = ClTheme.spacing.xxl),
            verticalArrangement = Arrangement.spacedBy(ClTheme.spacing.l),
        ) {
            Spacer(modifier = Modifier.height(ClTheme.spacing.xxxl))

            Text(
                text = "畅撩交友 ✦",
                style = ClTheme.typography.pageTitle,
                color = ClTheme.colors.ink,
            )
            Text(
                text = "遇见同频的人，聊聊今天的心情",
                style = ClTheme.typography.caption,
                color = ClTheme.colors.muted,
            )

            Spacer(modifier = Modifier.height(ClTheme.spacing.m))

            ClTextField(
                label = "手机号 / 账号",
                value = state.account,
                onValueChange = onAccountChange,
                placeholder = "请输入手机号或账号",
                error = state.accountError,
                maxLength = 32,
            )

            ClPasswordField(
                label = "密码",
                value = state.password,
                onValueChange = onPasswordChange,
                placeholder = "请输入密码",
                error = state.passwordError,
                maxLength = 20,
            )

            ClCheckboxAgreement(
                checked = state.agreed,
                onCheckedChange = onAgreedChange,
                error = state.agreementError,
                onUserAgreement = onUserAgreement,
                onPrivacy = onPrivacy,
            )

            ClPrimaryButton(
                text = "登录",
                loading = state.submitting,
                enabled = !state.submitting,
                onClick = onSubmit,
            )

            Box(
                modifier = Modifier.fillMaxWidth(),
                contentAlignment = Alignment.Center,
            ) {
                ClTextButton(text = "注册新账号", onClick = onRegister)
            }

            Spacer(modifier = Modifier.height(ClTheme.spacing.xxxl))
        }
    }
}
